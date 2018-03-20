<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests\Form;

use Contao\BackendUser;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\DataContainer;
use Contao\File;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Exception\AjaxExitException;
use HeimrichHannot\AjaxBundle\Manager\AjaxActionManager;
use HeimrichHannot\AjaxBundle\Manager\AjaxManager;
use HeimrichHannot\AjaxBundle\Manager\AjaxTokenManager;
use HeimrichHannot\MultiFileUpload\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUpload\Form\FormMultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Arrays\ArrayUtil;
use HeimrichHannot\UtilsBundle\Classes\ClassUtil;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\File\FileUtil;
use HeimrichHannot\UtilsBundle\String\StringUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class UploadTest extends ContaoTestCase
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var AjaxActionManager
     */
    protected $ajaxAction;

    public static function tearDownAfterClass(): void
    {
        // The temporary directory would not be removed without this call!
        parent::tearDownAfterClass();
    }

    public function setUp()
    {
        global $objPage;

        $objPage = $this->mockClassWithProperties(PageModel::class, ['outputFormat' => 'no']);

        parent::setUp();

        if (!defined('TL_ROOT')) {
            define('TL_ROOT', $this->getTempDir());
        }

        if (!defined('TL_MODE')) {
            define('TL_MODE', 'FE');
        }

        if (!defined('TL_ERROR')) {
            define('TL_ERROR', 'ERROR');
        }

        if (!defined('UNIT_TESTING')) {
            define('UNIT_TESTING', true);
        }

        if (!defined('UNIT_TESTING_FILES')) {
            define('UNIT_TESTING_FILES', $this->getTempDir().'/files');
        }
        $GLOBALS['TL_LANG']['ERR']['illegalMimeType'] = 'Unerlaubter Dateityp: %s';
        $GLOBALS['TL_LANG']['ERR']['illegalFileExtension'] = 'Unerlaubte Dateiendung: %s';
        $GLOBALS['AJAX'] = ['multifileupload' => ['actions' => ['upload' => ['csrf_protection' => true]]]];

        $fs = new Filesystem();
        $fs->mkdir($this->getTempDir().'/files');
        $fs->mkdir($this->getTempDir().'/files/tmp');
        $fs->mkdir($this->getTempDir().'/files/uploads');
        $fs->mkdir($this->getTempDir().'/system/modules');
        $fs->mkdir($this->getTempDir().'/config');
        $fs->mkdir($this->getTempDir().'/themes');
        file_put_contents($this->getTempDir().'/files/tmp/.public', '');

        $GLOBALS['TL_LANG']['MSC']['dropzone']['labels'] = ['labels'];
        $GLOBALS['TL_LANG']['MSC']['dropzone']['messages'] = [];
        $GLOBALS['TL_DCA']['tl_files']['config'] = [
            'onsubmit_callback' => [],
            'dataContainer' => 'File',
        ];

        $files = [
            '.~file   name#%&*{}:<>?+|".zip',
            'file___name.zip',
            'file...name.zip',
            'file name.zip',
            'file--.--.-.--name.zip',
            'file---name.zip',
            'file   name.zip',
            'file...name..zip',
            '.~file   name#%&*{}:<>?+|"\'.zip',
            'data.csv',
            'cmd_test.php.jpg',
            'cmd_test.php',
            'cmd_test1.php',
        ];

        $this->createTestFiles($files);

        $this->requestStack = new RequestStack();
        $this->requestStack->push(new \Symfony\Component\HttpFoundation\Request());

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $this->request = new Request($this->mockContaoFramework(), $this->requestStack, $scopeMatcher);
        $this->ajaxAction = new AjaxActionManager();

        $tokenManager = $this->mockAdapter(['getToken', 'getValue', 'isTokenValid']);
        $tokenManager->method('getToken')->willReturnSelf();
        $tokenManager->method('getValue')->willReturn('token');
        $tokenManager->method('isTokenValid')->willReturn(true);

        $database = $this->mockAdapter(['fieldExists', 'listFields']);
        $database->method('fieldExists')->willReturn(true);
        $database->method('listFields')->willReturn([]);

        $framework = $this->mockContaoFramework();
        $framework->method('createInstance')->willReturn($database);

        $loggerAdapter = $this->mockAdapter(['log']);

        $tokenChecker = $this->mockAdapter(['getBackendUsername']);
        $tokenChecker->method('getBackendUsername')->willReturn(null);

        $modelUtils = $this->mockAdapter(['findModelInstancesBy']);
        $modelUtils->method('findModelInstancesBy')->willReturn('model');

        $container = $this->mockContainer($this->getTempDir());
        $container->set('huh.utils.url', new UrlUtil($this->mockContaoFramework()));
        $container->set('request_stack', $this->requestStack);
        $container->set('contao.csrf.token_manager', $tokenManager);
        $container->set('contao.framework', $framework);
        $container->set('session', new Session(new MockArraySessionStorage()));
        $container->set('database_connection', $this->mockClassWithProperties(Database::class, []));
        $container->set('huh.utils.container', new ContainerUtil($this->mockContaoFramework()));
        $container->set('huh.ajax', new AjaxManager());
        $container->set('huh.utils.file', new FileUtil($this->mockContaoFramework()));
        $container->set('huh.utils.string', new StringUtil($this->mockContaoFramework()));
        $container->set('huh.utils.array', new ArrayUtil($this->mockContaoFramework()));
        $container->set('filesystem', new Filesystem());
        $container->setParameter('contao.resources_paths', [$this->getTempDir()]);
        $container->setParameter('kernel.logs_dir', $this->getTempDir());
        $container->set('monolog.logger.contao', $loggerAdapter);
        $container->set('security.token_storage', new TokenStorage());
        $container->set('contao.security.token_checker', $tokenChecker);
        $container->set('huh.utils.model', $modelUtils);
        System::setContainer($container);

        $requestStack = new RequestStack();
        $requestStack->push(new \Symfony\Component\HttpFoundation\Request());

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);

        $container = System::getContainer();
        $container->set('huh.request', $request);
        $container->set('huh.ajax.token', new AjaxTokenManager());
        System::setContainer($container);

        if (!\function_exists('ampersand')) {
            include_once __DIR__.'/../../vendor/contao/core-bundle/src/Resources/contao/helper/functions.php';
        }

        if (!\interface_exists('uploadable')) {
            include_once __DIR__.'/../../vendor/contao/core-bundle/src/Resources/contao/helper/interface.php';
        }
    }

    /**
     * test upload controller against cross-site request.
     *
     * @test
     */
    public function testUploadHTMLInjection()
    {
        $strAction = $this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD);

        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$strAction, 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file   name.zip', // Name of the sent file
                '"b<marquee onscroll=alert(1)>file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles[0]]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $container = System::getContainer();
        $container->set('huh.request', $request);
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'zip',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(\HeimrichHannot\AjaxBundle\Exception\AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('bmarquee-onscrollalert1file-name.zip', $objJson->result->data->filenameSanitized);
        }
    }

    /**
     * test upload controller against cross-site request.
     *
     * @test
     */
    public function testInvalidAjaxUploadToken()
    {
        $strAction = $this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD);
        $strAction = System::getContainer()->get('huh.utils.url')->removeQueryString([AjaxManager::AJAX_ATTR_TOKEN], $strAction);
        $strAction = System::getContainer()->get('huh.utils.url')->addQueryString(AjaxManager::AJAX_ATTR_TOKEN.'='. 12355456, $strAction);

        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$strAction, 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $container = System::getContainer();
        $container->set('huh.request', $request);
        System::setContainer($container);

        // simulate upload of php file hidden in an image file
        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file   name.zip', // Name of the sent file
                'file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file---name.zip', // Name of the sent file
                'file---name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file--.--.-.--name.zip', // Name of the sent file
                'file--.--.-.--name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file...name..zip', // Name of the sent file
                'file...name..zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file___name.zip', // Name of the sent file
                'file___name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/.~file   name#%&*{}:<>?+|"\'.zip', // Name of the sent file
                '.~file   name#%&*{}:<>?+|"\'.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles]);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'zip',
                'fieldType' => 'checkbox',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
                'maxFiles' => 8,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        $tokenManager = $this->mockAdapter(['getToken', 'getValue', 'isTokenValid']);
        $tokenManager->method('getToken')->willReturnSelf();
        $tokenManager->method('getValue')->willReturn('token');
        $tokenManager->method('isTokenValid')->willReturn(false);

        $container = System::getContainer();
        $container->set('contao.csrf.token_manager', $tokenManager);
        System::setContainer($container);

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('Invalid ajax token.', $objJson->message);
        }
    }

    /**
     * test upload controller against cross-site disk flooding.
     *
     * @test
     */
    public function testDiskFlooding()
    {
        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file   name.zip', // Name of the sent file
                'file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file---name.zip', // Name of the sent file
                'file---name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file--.--.-.--name.zip', // Name of the sent file
                'file--.--.-.--name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file...name..zip', // Name of the sent file
                'file...name..zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file___name.zip', // Name of the sent file
                'file___name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/.~file   name#%&*{}:<>?+|"\'.zip', // Name of the sent file
                '.~file   name#%&*{}:<>?+|"\'.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $container = System::getContainer();
        $container->set('huh.request', $request);
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'zip',
                'fieldType' => 'checkbox',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
                'maxFiles' => 2,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('Bulk file upload violation.', $objJson->message);
        }
    }

    /**
     * @test
     */
    public function testSanitizeFileNames()
    {
        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file   name.zip', // Name of the sent file
                'file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file---name.zip', // Name of the sent file
                'file---name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file--.--.-.--name.zip', // Name of the sent file
                'file--.--.-.--name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file...name..zip', // Name of the sent file
                'file...name..zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/file___name.zip', // Name of the sent file
                'file___name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/.~file   name#%&*{}:<>?+|"\'.zip', // Name of the sent file
                '.~file   name#%&*{}:<>?+|"\'.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles]);

        $objRequest->files->add(['files' => $arrFiles]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $container = System::getContainer();
        $container->set('huh.request', $request);
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'zip',
                'fieldType' => 'checkbox',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
                'maxFiles' => 8,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('file-name.zip', $objJson->result->data[0]->filenameSanitized);
            $this->assertSame('file-name.zip', $objJson->result->data[1]->filenameSanitized);
            $this->assertSame('file-name.zip', $objJson->result->data[2]->filenameSanitized);
            $this->assertSame('file-name.zip', $objJson->result->data[3]->filenameSanitized);
            $this->assertSame('file___name.zip', $objJson->result->data[4]->filenameSanitized);
            $this->assertSame('file-name.zip', $objJson->result->data[5]->filenameSanitized);
        }
    }

    /**
     * @test
     */
    public function testMaliciousFileUploadOfInvalidCharactersInFileName()
    {
        file_put_contents($this->getTempDir().'/files/საბეჭდი_მანქანა.png', 'Testfile');

        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        // simulate upload of php file hidden in an image file
        $file = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/საბეჭდი_მანქანა.png', // Name of the sent file
            'საბეჭდი_მანქანა.png', // mime type
            'image/png', // size of the file
            64693, null, true);

        $objRequest->files->add(['files' => $file]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $modelAdapter = $this->mockAdapter(['find']);
        $modelAdapter->method('find')->willReturn(null);

        $framework = $this->mockContaoFramework();
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case Database::class:
                    $database = $this->mockAdapter(['fieldExists', 'listFields']);
                    $database->method('fieldExists')->willReturn(true);
                    $database->method('listFields')->willReturn([]);

                    return $database;
                case File::class:
                    $fileModel = $this->mockClassWithProperties(FilesModel::class, ['uuid' => '4923hef8fh827fhf448f0438h']);
                    $file = $this->mockClassWithProperties(File::class, ['getModel' => 'true']);
                    $file->method('getModel')->willReturn($fileModel);

                    return $file;
                default:
                    return null;
            }
        });

        $request = new Request($framework, $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $filesModel = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/cmd_test.php', 'uuid' => 'fuuf4h3pfuh34f4uh4f444', 'filesize' => '1024']);
        $file = $this->mockClassWithProperties(File::class, ['value' => 'value', 'name' => 'data']);
        $file->method('getModel')->willReturn($filesModel);
        $file->method('exists')->willReturn(true);

        $fileUtils = $this->mockAdapter(['getFileFromUuid', 'sanitizeFileName', 'addUniqueIdToFilename']);
        $fileUtils->method('getFileFromUuid')->willReturn($file);
        $fileUtils->method('sanitizeFileName')->willReturnCallback(function ($filename) {
            $fileUtils = new FileUtil($this->mockContaoFramework());
            $filename = $fileUtils->sanitizeFileName($filename);

            return $filename;
        });
        $fileUtils->method('addUniqueIdToFilename')->willReturnCallback(function ($name, $prefix) {
            $file = new FileUtil($this->mockContaoFramework());

            return $file->addUniqueIdToFilename($name, $prefix);
        });
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = 80;

        $container = System::getContainer();
        $container->set('huh.request', $request);
        $container->set('contao.framework', $framework);
        $container->set('huh.utils.file', $fileUtils);
        $container->set('huh.ajax.action', new AjaxActionManager());
        $container->set('huh.utils.class', new ClassUtil());
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'jpg,jpeg,gif,png',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
                'maxFiles' => 6,
                'isSubmitCallback' => true,
            ],
            'options_callback' => '',
            'options' => '',
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('_.png', $objJson->result->data->filenameSanitized);
        }
    }

    /**
     * @test
     */
    public function testUploadCSVFile()
    {
        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        @copy(__DIR__.'/../files/data.csv', $this->getTempDir().'/files/data.csv');

        // simulate upload of php file hidden in an image file
        $file = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/data.csv', // Name of the sent file
            'data.csv', // mime type
            'text/csv', // size of the file
            7006, null, true);

        $objRequest->files->add(['files' => $file]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $modelAdapter = $this->mockAdapter(['find']);
        $modelAdapter->method('find')->willReturn(null);

        $framework = $this->mockContaoFramework();
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case Database::class:
                    $database = $this->mockAdapter(['fieldExists', 'listFields']);
                    $database->method('fieldExists')->willReturn(true);
                    $database->method('listFields')->willReturn([]);

                    return $database;
                case File::class:
                    $fileModel = $this->mockClassWithProperties(FilesModel::class, ['uuid' => '4923hef8fh827fhf448f0438h']);
                    $file = $this->mockClassWithProperties(File::class, ['getModel' => 'true']);
                    $file->method('getModel')->willReturn($fileModel);

                    return $file;
                default:
                    return null;
            }
        });

        $request = new Request($framework, $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $filesModel = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/cmd_test.php', 'uuid' => 'fuuf4h3pfuh34f4uh4f444', 'filesize' => '1024']);
        $file = $this->mockClassWithProperties(File::class, ['value' => 'value', 'name' => 'data']);
        $file->method('getModel')->willReturn($filesModel);
        $file->method('exists')->willReturn(true);

        $fileUtils = $this->mockAdapter(['getFileFromUuid', 'sanitizeFileName', 'addUniqueIdToFilename']);
        $fileUtils->method('getFileFromUuid')->willReturn($file);
        $fileUtils->method('sanitizeFileName')->willReturnCallback(function ($filename) { return $filename; });
        $fileUtils->method('addUniqueIdToFilename')->willReturnCallback(function ($name, $prefix) {
            $file = new FileUtil($this->mockContaoFramework());

            return $file->addUniqueIdToFilename($name, $prefix);
        });
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = 80;

        $container = System::getContainer();
        $container->set('huh.request', $request);
        $container->set('contao.framework', $framework);
        $container->set('huh.utils.file', $fileUtils);
        $container->set('huh.ajax.action', new AjaxActionManager());
        $container->set('huh.utils.class', new ClassUtil());
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'csv',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
                'maxFiles' => 6,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame(200, $objJson->statusCode);
        }
    }

    /**
     * @test
     */
    public function testMaliciousFileUploadOfDisguisedPhpFile()
    {
        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        @copy(__DIR__.'/../files/cmd_test.php.jpg', UNIT_TESTING_FILES.'/cmd_test.php.jpg');

        // simulate upload of php file hidden in an image file
        $file = new UploadedFile(UNIT_TESTING_FILES.'/cmd_test.php.jpg', // Path to the file to send
            'cmd_test.php.jpg', // Name of the sent file
            'image/jpeg',  // mime type
            652,// size of the file
            null, true);

        $objRequest->files->add(['files' => $file]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $container = System::getContainer();
        $container->set('huh.request', $request);
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'jpg,jpeg,gif,png',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
                'maxFiles' => 6,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('Unerlaubter Dateityp: text/x-php', $objJson->result->data->error);
            $this->assertSame('cmd_test-php.jpg', $objJson->result->data->filenameSanitized);
        }
    }

    /**
     * @test
     */
    public function testMaliciousFileUploadOfInvalidTypes()
    {
        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        // prevent test file removal
        @copy(__DIR__.'/../files/cmd_test.php.jpg', UNIT_TESTING_FILES.'/cmd_test.php');
        @copy(__DIR__.'/../files/cmd_test.php.jpg', UNIT_TESTING_FILES.'/cmd_test1.php');

        $file = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/cmd_test.php', // Name of the sent file
            'cmd_test.php', // mime type
            'text/x-php', // size of the file
            652, null, true);

        $file2 = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/cmd_test1.php', // Name of the sent file
            'cmd_test1.php', // mime type
            'text/x-php', // size of the file
            652, null, true);

        $objRequest->files->add(['files' => [$file, $file2]]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $container = System::getContainer();
        $container->set('huh.request', $request);
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'jpg,jpeg,gif,png',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
                'maxFiles' => 6,
                'isSubmitCallback' => true,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');

        $arrAttributes['strTable'] = 'tl_files';

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            $objUploader->upload();
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('Unerlaubte Dateiendung: php', $objJson->result->data[0]->error);
            $this->assertSame('cmd_test.php', $objJson->result->data[0]->filenameSanitized);

            $this->assertSame('Unerlaubte Dateiendung: php', $objJson->result->data[1]->error);
            $this->assertSame('cmd_test1.php', $objJson->result->data[1]->filenameSanitized);
        }
    }

    public function testExecutePostActionsHook()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'csv',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files');
        $arrAttributes['strTable'] = 'tl_files';
        $class = new BackendMultiFileUpload($arrAttributes);
        $this->assertFalse($class->executePostActionsHook('test', $this->getDataContainer()));

        System::getContainer()->get('session')->set('multifileupload_fields', ['tl_files' => ['field' => $arrDca]]);
        try {
            $class->executePostActionsHook('multifileupload_upload', $this->getDataContainer());
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $exception) {
            $message = json_decode($exception->getMessage());
            $this->assertSame('Bad Request', $message->message);
        }

        $ajaxActionManager = new AjaxActionManager();
        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$ajaxActionManager->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        // simulate upload of php file hidden in an image file
        $file = new UploadedFile(// Path to the file to send
            $this->getTempDir().'/files/data.csv', // Name of the sent file
            'data.csv', // mime type
            'text/csv', // size of the file
            7006, null, true);

        $objRequest->files->add(['files' => $file]);

        $requestStack = new RequestStack();
        $requestStack->push($objRequest);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $modelAdapter = $this->mockAdapter(['find']);
        $modelAdapter->method('find')->willReturn(null);

        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');

        $widgetAdapter = $this->mockAdapter(['getAttributesFromDca']);
        $widgetAdapter->method('getAttributesFromDca')->willReturn($arrAttributes);

        $framework = $this->mockContaoFramework([Widget::class => $widgetAdapter]);
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case Database::class:
                    $database = $this->mockAdapter(['fieldExists', 'listFields']);
                    $database->method('fieldExists')->willReturn(true);
                    $database->method('listFields')->willReturn([]);

                    return $database;
                case File::class:
                    $fileModel = $this->mockClassWithProperties(FilesModel::class, ['uuid' => '4923hef8fh827fhf448f0438h']);
                    $file = $this->mockClassWithProperties(File::class, ['getModel' => 'true']);
                    $file->method('getModel')->willReturn($fileModel);

                    return $file;
                default:
                    return null;
            }
        });

        $request = new Request($framework, $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', \RequestToken::get());
        $request->request->set('files', []);
        $request->setPost('field', 'field');
//        $request->setGet(AjaxManager::AJAX_ATTR_ACT, 'upload');

        $filesModel = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/cmd_test.php', 'uuid' => 'fuuf4h3pfuh34f4uh4f444', 'filesize' => '1024']);
        $file = $this->mockClassWithProperties(File::class, ['value' => 'value', 'name' => 'data']);
        $file->method('getModel')->willReturn($filesModel);
        $file->method('exists')->willReturn(true);

        $fileUtils = $this->mockAdapter(['getFileFromUuid', 'sanitizeFileName', 'addUniqueIdToFilename']);
        $fileUtils->method('getFileFromUuid')->willReturn($file);
        $fileUtils->method('sanitizeFileName')->willReturnCallback(function ($filename) { return $filename; });
        $fileUtils->method('addUniqueIdToFilename')->willReturnCallback(function ($name, $prefix) {
            $file = new FileUtil($this->mockContaoFramework());

            return $file->addUniqueIdToFilename($name, $prefix);
        });
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = 80;
        $ajaxManager = $this->mockAdapter(['runActiveAction']);

        $container = System::getContainer();
        $container->set('huh.request', $request);
        $container->set('contao.framework', $framework);
        $container->set('huh.utils.file', $fileUtils);
        $container->set('huh.ajax.action', $ajaxActionManager);
        $container->set('huh.utils.class', new ClassUtil());
        $container->set('huh.ajax', $ajaxManager);
        System::setContainer($container);

        try {
            $user = BackendUser::getInstance();
            $user->admin = true;
            $class->executePostActionsHook('multifileupload_upload', $this->getDataContainer());
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $exception) {
            $message = json_decode($exception->getMessage());
            $this->assertSame(200, $message->statusCode);
        }

        System::getContainer()->get('huh.request')->files->remove('files');

        $this->assertNull($class->executePostActionsHook('multifileupload_upload', $this->getDataContainer()));
    }

    public function testInstantiate()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'csv',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $value = json_encode('value');
        $arrAttributes['value'] = $value;
        unset($arrAttributes['strTable']);
        $class = new FormMultiFileUpload($arrAttributes);
        $this->assertInstanceOf(FormMultiFileUpload::class, $class);

        $arrAttributes['value'] = [$value];
        $class = new FormMultiFileUpload($arrAttributes);
        $this->assertInstanceOf(FormMultiFileUpload::class, $class);

        $GLOBALS['TL_LANG']['ERR']['noUploadFolderDeclared'] = 'Kein "uploadFolder" für das Feld "%s" in eval angegeben.';
        try {
            $class = new FormMultiFileUpload([]);
        } catch (\Exception $e) {
            $this->assertSame('Kein "uploadFolder" für das Feld "" in eval angegeben.', $e->getMessage());
        }

        $containerUtils = $this->mockAdapter(['isFrontend']);
        $containerUtils->method('isFrontend')->willReturn(true);

        $ajaxAdapter = $this->mockAdapter(['runActiveAction']);
        $requestAdapter = $this->mockAdapter(['getGet']);
        $requestAdapter->method('getGet')->willReturn('');

        $container = $this->mockContainer();
        $container->set('huh.utils.container', $containerUtils);
        $container->set('huh.ajax.action', new AjaxActionManager());
        $container->set('huh.ajax', $ajaxAdapter);
        $container->set('huh.request', $requestAdapter);
        $container->set('huh.ajax.token', new AjaxTokenManager());
        $container->set('session', new Session(new MockArraySessionStorage()));
        System::setContainer($container);

        $class = new FormMultiFileUpload();
        $this->assertInstanceOf(FormMultiFileUpload::class, $class);
    }

    public function testMoveFiles()
    {
        System::getContainer()->get('huh.request')->setPost('title', []);
        System::getContainer()->get('huh.request')->setPost('upload', []);

        $GLOBALS['TL_DCA']['tl_files']['fields'] = [
            'title' => [
                'inputType' => 'text',
            ],
            'upload' => [
                'inputType' => 'multifileupload',
                'upload_path_callback' => [],
                'eval' => [
                    'uploadFolder' => $this->getTempDir().'/files/uploads',
                ],
            ],
        ];

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'csv',
                'fieldType' => 'radio',
                'submitOnChange' => false,
                'onchange' => '',
                'allowHtml' => false,
                'rte' => '',
                'preserveTags' => '',
                'sql' => 'varchar(255)',
                'encrypt' => false,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $value = json_encode('value');
        $arrAttributes['value'] = $value;
        unset($arrAttributes['strTable']);

        $filesModel = $this->mockClassWithProperties(\Contao\Model\Collection::class, []);
        $filesModel->method('fetchEach')->willReturn(['files/data.csv']);

        $filesAdapter = $this->mockAdapter(['findMultipleByUuids']);
        $filesAdapter->method('findMultipleByUuids')->willReturn($filesModel);

        $filesUtil = $this->mockAdapter(['getUniqueFileNameWithinTarget', 'getFolderFromDca']);
        $filesUtil->method('getUniqueFileNameWithinTarget')->willReturn('files/uploads/dataMove.csv');
        $filesUtil->method('getFolderFromDca')->willReturn('files/uploads');

        $container = System::getContainer();
        $container->set('contao.framework', $this->mockContaoFramework([FilesModel::class => $filesAdapter]));
        $container->set('huh.utils.file', $filesUtil);
        System::setContainer($container);

        $class = new FormMultiFileUpload($arrAttributes);
        $class->moveFiles($this->getDataContainer());
    }

    /**
     * @return DataContainer| \PHPUnit_Framework_MockObject_MockObject
     */
    public function getDataContainer()
    {
        $activeRecord = new \stdClass();
        $activeRecord->upload = serialize('files');

        return $this->mockClassWithProperties(DataContainer::class, ['table' => 'tl_files', 'activeRecord' => $activeRecord, 'id' => 12]);
    }

    /**
     * creates files for tests.
     *
     * @param array $files
     */
    protected function createTestFiles(array $files)
    {
        foreach ($files as $file) {
            $result = fopen($this->getTempDir().'/files/'.$file, 'c');
        }
    }
}
