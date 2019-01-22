<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests\Widget;

use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\DataContainer;
use Contao\File;
use Contao\FilesModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Exception\AjaxExitException;
use HeimrichHannot\AjaxBundle\Manager\AjaxActionManager;
use HeimrichHannot\AjaxBundle\Manager\AjaxManager;
use HeimrichHannot\AjaxBundle\Manager\AjaxTokenManager;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
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
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class BackendMultiFileUploadTestOld extends ContaoTestCase
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
        $fs = new Filesystem();
        $fs->remove(TL_ROOT);
    }

    public function setUp()
    {
        if (!\defined('TL_MODE')) {
            \define('TL_MODE', 'FE');
        }

        if (!\defined('UNIT_TESTING')) {
            \define('UNIT_TESTING', true);
        }

        parent::setUp();

        $GLOBALS['TL_LANG']['ERR']['illegalMimeType'] = 'Unerlaubter Dateityp: %s';
        $GLOBALS['TL_LANG']['ERR']['illegalFileExtension'] = 'Unerlaubte Dateiendung: %s';
        $GLOBALS['AJAX'] = ['multifileupload' => ['actions' => ['upload' => ['csrf_protection' => true]]]];

        $fs = new Filesystem();
        $fs->mkdir(TL_ROOT.'/files');
        $fs->mkdir(TL_ROOT.'/files/tmp');
        $fs->mkdir(TL_ROOT.'/files/uploads');
        $fs->mkdir(TL_ROOT.'/system/modules');
        $fs->mkdir(TL_ROOT.'/config');
        $fs->mkdir(TL_ROOT.'/themes');
        file_put_contents(TL_ROOT.'/files/tmp/.public', '');

        $GLOBALS['TL_LANG']['MSC']['dropzone']['labels'] = ['labels'];
        $GLOBALS['TL_LANG']['MSC']['dropzone']['messages'] = [];
        $GLOBALS['TL_DCA']['tl_files']['config'] = [
            'onsubmit_callback' => ['files'],
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

        $controller = $this->mockAdapter(['replaceInsertTags']);
        $controller->method('replaceInsertTags')->willReturnCallback(function ($string, $bln) {
            return Controller::replaceInsertTags($string, $bln);
        });

        $framework = $this->mockContaoFramework([Controller::class => $controller]);
        $framework->method('createInstance')->willReturn($database);

        $loggerAdapter = $this->mockAdapter(['log']);

        $tokenChecker = $this->mockAdapter(['getBackendUsername']);
        $tokenChecker->method('getBackendUsername')->willReturn(null);

        $modelUtils = $this->mockAdapter(['findModelInstancesBy']);
        $modelUtils->method('findModelInstancesBy')->willReturn('model');

        $container = $this->mockContainer(TL_ROOT);
        $container->set('request_stack', $this->requestStack);
        $container->set('contao.csrf.token_manager', $tokenManager);
        $container->set('security.csrf.token_manager', $tokenManager);
        $container->set('contao.framework', $framework);
        $container->set('session', new Session(new MockArraySessionStorage()));
        $container->set('database_connection', $this->mockClassWithProperties(Database::class, []));
        $container->set('huh.ajax', new AjaxManager());
        $container->set('huh.utils.file', new FileUtil($this->mockContaoFramework()));
        $container->set('huh.utils.string', new StringUtil($this->mockContaoFramework()));
        $container->set('filesystem', new Filesystem());

        $filelocator = new FileLocator($this->createMock(KernelInterface::class));

        $container->set('huh.utils.container', new ContainerUtil($this->mockContaoFramework(), $filelocator, $scopeMatcher));
        $container->set('huh.utils.url', new UrlUtil($this->mockContaoFramework()));
        $container->setParameter('contao.resources_paths', [TL_ROOT]);
        $container->setParameter('kernel.logs_dir', TL_ROOT);
        $container->setParameter('huh.multifileupload.mime_theme_default', 'system/modules/multifileupload/assets/img/mimetypes/Numix-uTouch');
        $container->setParameter('huh.multifileupload.upload_tmp', 'files/tmp');
        $container->setParameter('huh.multifileupload.max_files_default', '10');
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
            TL_ROOT.'/files/data.csv', // Name of the sent file
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

        $controller = $this->mockAdapter(['replaceInsertTags']);
        $controller->method('replaceInsertTags')->willReturnCallback(function ($string, $bln) {
            return Controller::replaceInsertTags($string, $bln);
        });

        $framework = $this->mockContaoFramework([Widget::class => $widgetAdapter, Controller::class => $controller]);
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

        try {
            $this->assertNull($class->executePostActionsHook('multifileupload_upload', $this->getDataContainer()));
        } catch (AjaxExitException $exception) {
            $message = json_decode($exception->getMessage());
            $this->assertSame('Invalid Request. File not found in \Symfony\Component\HttpFoundation\FileBag', $message->message);
        }
    }

    /**
     * @return DataContainer| \PHPUnit_Framework_MockObject_MockObject
     */
    public function getDataContainer($activeRecord = null)
    {
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
            $result = fopen(TL_ROOT.'/files/'.$file, 'cb');
        }
    }
}
