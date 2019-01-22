<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests\Form;

use Contao\Controller;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\DataContainer;
use Contao\Dbafs;
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
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Arrays\ArrayUtil;
use HeimrichHannot\UtilsBundle\Classes\ClassUtil;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\File\FileUtil;
use HeimrichHannot\UtilsBundle\String\StringUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class FormMultiFileUploadTest extends ContaoTestCase
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
        global $objPage;

        if (!\defined('TL_MODE')) {
            \define('TL_MODE', 'FE');
        }

        if (!\defined('UNIT_TESTING')) {
            \define('UNIT_TESTING', true);
        }

        $objPage = $this->mockClassWithProperties(PageModel::class, ['outputFormat' => 'html5']);

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
        $container->set('huh.utils.url', new UrlUtil($this->mockContaoFramework()));
        $container->set('request_stack', $this->requestStack);
        $container->set('contao.csrf.token_manager', $tokenManager);
        $container->set('security.csrf.token_manager', $tokenManager);
        $container->set('contao.framework', $framework);
        $container->set('session', new Session(new MockArraySessionStorage()));
        $container->set('database_connection', $this->mockClassWithProperties(Database::class, []));
        $container->set('huh.utils.container', new ContainerUtil($this->mockContaoFramework()));
        $container->set('huh.ajax', new AjaxManager());
        $container->set('huh.utils.file', new FileUtil($this->mockContaoFramework()));
        $container->set('huh.utils.string', new StringUtil($this->mockContaoFramework()));
        $container->set('huh.utils.array', new ArrayUtil($this->mockContaoFramework()));
        $container->set('filesystem', new Filesystem());
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

        $GLOBALS['TL_DCA']['tl_files']['config']['onsubmit_callback'] = '';
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $class = new FormMultiFileUpload($arrAttributes);
        $this->assertInstanceOf(FormMultiFileUpload::class, $class);
        $this->assertSame(['multifileupload_moveFiles' => ['HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload', 'moveFiles']], $GLOBALS['TL_DCA']['tl_files']['config']['onsubmit_callback']);

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
                    'uploadFolder' => TL_ROOT.'/files/uploads',
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
        $filesUtil->method('getUniqueFileNameWithinTarget')->willReturn('files/uploads/data.csv');
        $filesUtil->method('getFolderFromDca')->willReturn('files');

        $container = System::getContainer();
        $container->set('contao.framework', $this->mockContaoFramework([FilesModel::class => $filesAdapter]));
        $container->set('huh.utils.file', $filesUtil);
        System::setContainer($container);
        $activeRecord = new \stdClass();
        $activeRecord->upload = serialize('files');
        $class = new FormMultiFileUpload($arrAttributes);
        $this->assertNull($class->moveFiles($this->getDataContainer($activeRecord)));

        $filesAdapter = $this->mockAdapter(['findMultipleByUuids']);
        $filesAdapter->method('findMultipleByUuids')->willReturn(null);

        $container = System::getContainer();
        $container->set('contao.framework', $this->mockContaoFramework([FilesModel::class => $filesAdapter]));
        System::setContainer($container);
        $this->assertNull($class->moveFiles($this->getDataContainer($activeRecord)));

        try {
            $filesUtil = $this->mockAdapter(['getUniqueFileNameWithinTarget', 'getFolderFromDca']);
            $filesUtil->method('getFolderFromDca')->willReturn(null);
            $container = System::getContainer();
            $container->set('huh.utils.file', $filesUtil);
            System::setContainer($container);
            $class->moveFiles($this->getDataContainer($activeRecord));
        } catch (\Exception $exception) {
            $this->assertSame('Undefined index: uploadNoUploadFolderDeclared', $exception->getMessage());
        }
    }

    public function testGenerateLabel()
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
        $class = new FormMultiFileUpload($arrAttributes);
        $this->assertSame('<label class="">label</label>', $class->generateLabel());

        $class = new FormMultiFileUpload();
        $this->assertSame('', $class->generateLabel());
    }

    public function testValidator()
    {
        $GLOBALS['TL_LANG']['ERR']['invalidUuid'] = 'Der Datei wurde kein eindeutiger Kennzeichner (uuid) zugewiesen, bitte versuchen Sie die Datei erneut hochzuladen.';

        $fileUtils = $this->mockAdapter(['getFileFromUuid']);
        $fileUtils->method('getFileFromUuid')->willReturn(null);

        $container = System::getContainer();
        $container->set('huh.utils.file', $fileUtils);
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
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $class = new FormMultiFileUpload($arrAttributes);
        $class->useRawRequestData = true;
        $this->assertSame([], $class->validator(''));
        $this->assertFalse($class->validator(json_encode(['/files/data.csv'])));
        $this->assertFalse($class->validator(json_encode('/files/data.csv')));
        $this->assertFalse($class->validator(json_encode('dfd1cc5e-2c49-11e8-b467-0ed5f89f718b')));
        $this->assertSame([], $class->validator(json_encode(['dfd1cc5e-2c49-11e8-b467-0ed5f89f718b'])));

        $file = $this->mockClassWithProperties(File::class, []);
        $file->method('exists')->willReturn(true);

        $fileUtils = $this->mockAdapter(['getFileFromUuid']);
        $fileUtils->method('getFileFromUuid')->willReturn($file);

        $container = System::getContainer();
        $container->set('huh.utils.file', $fileUtils);
        System::setContainer($container);

        $this->assertSame(\Contao\StringUtil::uuidToBin('dfd1cc5e-2c49-11e8-b467-0ed5f89f718b'), $class->validator(json_encode('dfd1cc5e-2c49-11e8-b467-0ed5f89f718b')));
        $this->assertSame([\Contao\StringUtil::uuidToBin('dfd1cc5e-2c49-11e8-b467-0ed5f89f718b')], $class->validator(json_encode(['dfd1cc5e-2c49-11e8-b467-0ed5f89f718b'])));

        $GLOBALS['TL_LANG']['ERR']['mdtryNoLabel'] = 'error';
        $GLOBALS['TL_LANG']['ERR']['mandatory'] = 'error';
        $class->mandatory = true;
        $requestStack = System::getContainer()->get('request_stack')->getCurrentRequest();
        $requestStack->request->set('deleted_files', json_encode(['dfd1cc5e-2c49-11e8-b467-0ed5f89f718b']));
        $requestStack->request->set('deleted_', json_encode(['dfd1cc5e-2c49-11e8-b467-0ed5f89f718b']));
        $this->assertFalse($class->validator(json_encode(['dfd1cc5e-2c49-11e8-b467-0ed5f89f718b'])));

        $class = new FormMultiFileUpload();
        $class->useRawRequestData = true;
        $class->mandatory = true;
        $this->assertFalse($class->validator(json_encode(['dfd1cc5e-2c49-11e8-b467-0ed5f89f718b'])));
    }

    public function testGetUploader()
    {
        $class = new FormMultiFileUpload();
        $this->assertInstanceOf(MultiFileUpload::class, $class->getUploader());
    }

    public function testDeleteScheduledFiles()
    {
        $class = new FormMultiFileUpload();
        $this->assertSame([], $class->deleteScheduledFiles([]));

        $file = $this->createMock(File::class);
        $file->method('delete')->willReturn(true);
        $file->method('exists')->willReturn(true);
        $fileUtils = $this->mockAdapter(['getFileFromUuid']);
        $fileUtils->method('getFileFromUuid')->willReturn($file);

        $container = System::getContainer();
        $container->set('huh.utils.file', $fileUtils);
        System::setContainer($container);

        $this->assertNull($class->deleteScheduledFiles(['files']));
    }

    public function testValidateUpload()
    {
        $GLOBALS['TL_LANG']['ERR']['minWidth'] = 'Die Breite des Bildes darf %s Pixel nicht unterschreiten (aktuelle Bildbreite: %s Pixel).';
        $GLOBALS['TL_LANG']['ERR']['minHeight'] = 'Die Höhe des Bildes darf %s Pixel nicht unterschreiten (aktuelle Bildhöhe: %s Pixel).';
        $GLOBALS['TL_LANG']['ERR']['maxWidth'] = 'Die Breite des Bildes darf %s Pixel nicht überschreiten (aktuelle Bildbreite: %s Pixel).';
        $GLOBALS['TL_LANG']['ERR']['maxHeight'] = 'Die Höhe des Bildes darf %s Pixel nicht überschreiten (aktuelle Bildhöhe: %s Pixel).';

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => TL_ROOT.'/files/uploads/',
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
        $class = new FormMultiFileUpload($arrAttributes);

        $file = $this->mockClassWithProperties(File::class, ['isImage' => false]);

        $function = $this->getMethod(FormMultiFileUpload::class, 'validateUpload');
        $this->assertFalse($function->invokeArgs($class, [$file]));

        $imageUtils = $this->mockAdapter(['getPixelValue']);
        $imageUtils->method('getPixelValue')->willReturn(1);
        $container = System::getContainer();
        $container->set('huh.utils.image', $imageUtils);
        System::setContainer($container);

        $file = $this->mockClassWithProperties(File::class, ['isImage' => true, 'width' => 10, 'height' => 10]);
        $this->assertSame('Die Breite des Bildes darf 1 Pixel nicht überschreiten (aktuelle Bildbreite: 10 Pixel).', $function->invokeArgs($class, [$file]));

        $imageUtils = $this->mockAdapter(['getPixelValue']);
        $imageUtils->method('getPixelValue')->willReturn(11);
        $container = System::getContainer();
        $container->set('huh.utils.image', $imageUtils);
        System::setContainer($container);

        $file = $this->mockClassWithProperties(File::class, ['isImage' => true, 'width' => 10, 'height' => 10]);
        $this->assertSame('Die Breite des Bildes darf 11 Pixel nicht unterschreiten (aktuelle Bildbreite: 10 Pixel).', $function->invokeArgs($class, [$file]));

        $imageUtils = $this->mockAdapter(['getPixelValue']);
        $imageUtils->method('getPixelValue')->willReturn(5);
        $container = System::getContainer();
        $container->set('huh.utils.image', $imageUtils);
        System::setContainer($container);

        $file = $this->mockClassWithProperties(File::class, ['isImage' => true, 'width' => 10, 'height' => 4]);
        $this->assertSame('Die Höhe des Bildes darf 5 Pixel nicht unterschreiten (aktuelle Bildhöhe: 4 Pixel).', $function->invokeArgs($class, [$file]));

        $imageUtils = $this->mockAdapter(['getPixelValue']);
        $imageUtils->method('getPixelValue')->willReturn(5);
        $container = System::getContainer();
        $container->set('huh.utils.image', $imageUtils);
        System::setContainer($container);

        $file = $this->mockClassWithProperties(File::class, ['isImage' => true, 'width' => 5, 'height' => 6]);
        $this->assertSame('Die Höhe des Bildes darf 5 Pixel nicht überschreiten (aktuelle Bildhöhe: 6 Pixel).', $function->invokeArgs($class, [$file]));
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
        $request->query->remove(AjaxManager::AJAX_ATTR_ACT);

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

        $objUploader = new FormMultiFileUpload($arrAttributes);
        $result = $objUploader->upload();
        $result = $result->getResult();
        $data = $result->getData();
        $this->assertSame('bmarquee-onscrollalert1file-name.zip', $data['filenameSanitized']);
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
        $request->query->remove(AjaxManager::AJAX_ATTR_ACT);

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
            $objUploader->upload();
        } catch (AjaxExitException $exception) {
            $objJson = json_decode($exception->getMessage());
            $this->assertSame('Invalid Request Token!', $objJson->message);
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
        $request->query->remove(AjaxManager::AJAX_ATTR_ACT);

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
            $objUploader->upload();
        } catch (AjaxExitException $exception) {
            $objJson = json_decode($exception->getMessage());
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
        $request->query->remove(AjaxManager::AJAX_ATTR_ACT);

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

        $objUploader = new FormMultiFileUpload($arrAttributes);
        $result = $objUploader->upload();
        $result = $result->getResult();
        $data = $result->getData();

        $this->assertSame('file-name.zip', $data[0]['filenameSanitized']);
        $this->assertSame('file-name.zip', $data[1]['filenameSanitized']);
        $this->assertSame('file-name.zip', $data[2]['filenameSanitized']);
        $this->assertSame('file-name.zip', $data[3]['filenameSanitized']);
        $this->assertSame('file___name.zip', $data[4]['filenameSanitized']);
        $this->assertSame('file-name.zip', $data[5]['filenameSanitized']);
    }

    /**
     * @test
     */
    public function testMaliciousFileUploadOfInvalidCharactersInFileName()
    {
        file_put_contents(TL_ROOT.'/files/საბეჭდი_მანქანა.png', 'Testfile');

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
        $request->query->remove(AjaxManager::AJAX_ATTR_ACT);

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

        $objUploader = new FormMultiFileUpload($arrAttributes);
        $result = $objUploader->upload();
        $result = $result->getResult();
        $data = $result->getData();

        $this->assertSame('_.png', $data['filenameSanitized']);
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

        @copy(__DIR__.'/../files/data.csv', TL_ROOT.'/files/data.csv');

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
        $request->query->remove(AjaxManager::AJAX_ATTR_ACT);

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

        $objUploader = new FormMultiFileUpload($arrAttributes);
        $result = $objUploader->upload();
        $this->assertSame(200, $result->getStatusCode());
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

    public function testUploadFile()
    {
        $dca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'/uploads/',
                'extensions' => 'jpg',
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

        $attributes = Widget::getAttributesFromDca($dca, 'files');
        $formMultiFileUpload = new FormMultiFileUpload($attributes);
        $function = $this->getMethod(FormMultiFileUpload::class, 'uploadFile');

        $uploadFile = $this->createMock(UploadedFile::class);
        $uploadFile->method('getError')->willReturn('error');
        $uploadFile->method('getClientOriginalName')->willReturn('originalName');

        $this->assertSame(['error' => 'error', 'filenameOrigEncoded' => 'originalName', 'filenameSanitized' => 'originalname'], $function->invokeArgs($formMultiFileUpload, [$uploadFile, 'folder']));

        $uploadFile = $this->createMock(UploadedFile::class);
        $uploadFile->method('getError')->willReturn(false);
        $uploadFile->method('getClientOriginalName')->willReturn('originalName');
        $uploadFile->method('move')->willThrowException(new FileException('error'));
        $uploadFile->method('getClientOriginalExtension')->willReturn('csv');
        $uploadFile->method('getClientMimeType')->willReturn('csv');
        $uploadFile->method('getMimeType')->willReturn('csv');

        $this->assertSame(['error' => 'Unerlaubte Dateiendung: csv', 'filenameOrigEncoded' => 'originalName', 'filenameSanitized' => 'originalname'], $function->invokeArgs($formMultiFileUpload, [$uploadFile, 'folder']));

        $dca = [
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
                'isSubmitCallback' => true,
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
        ];

        $attributes = Widget::getAttributesFromDca($dca, 'files');
        $attributes['validateUploadCallback'] = [['MyClass', 'validateUpload'], [self::class, 'validateUpload'], [self::class, 'callbackFunction']];
        $formMultiFileUpload = new FormMultiFileUpload($attributes);

        // create new file
        file_put_contents(UNIT_TESTING_FILES.'/dataTest.csv', 'test');

        // throwing invalid argument exception case
        $uploadFile = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/dataTest.csv', // Name of the sent file
            'dataTest.csv', // mime type
            'text/csv', // size of the file
            7006, null, true);

        $dbafs = $this->mockAdapter(['shouldBeSynchronized', 'addResource']);
        $dbafs->method('shouldBeSynchronized')->willReturn(true);
        $dbafs->method('addResource')->willThrowException(new \InvalidArgumentException('invalid argument'));

        $framework = $this->mockContaoFramework([Dbafs::class => $dbafs]);
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case File::class:
                    $file = $this->mockClassWithProperties(File::class, []);
                    $file->method('getModel')->willReturn(null);

                    return $file;

                    break;

                default:
                    return null;

                    break;
            }
        });
        $GLOBALS['TL_LANG']['ERR']['outsideUploadDirectory'] = 'Speicherziel liegt außerhalb des Contao-Upload-Verzeichnisses.';
        $container = System::getContainer();
        $container->set('contao.framework', $framework);
        System::setContainer($container);

        $this->assertSame(['error' => 'Speicherziel liegt außerhalb des Contao-Upload-Verzeichnisses.', 'filenameOrigEncoded' => 'dataTest.csv', 'filenameSanitized' => 'datatest.csv'], $function->invokeArgs($formMultiFileUpload, [$uploadFile, 'folder']));
        $this->assertFileNotExists(UNIT_TESTING_FILES.'/dataTest.csv');
        // create new file
        file_put_contents(UNIT_TESTING_FILES.'/dataTest.csv', 'test');

        // throwing invalid argument exception case
        $uploadFile = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/dataTest.csv', // Name of the sent file
            'dataTest.csv', // mime type
            'text/csv', // size of the file
            7006, null, true);

        $dbafs = $this->mockAdapter(['shouldBeSynchronized', 'addResource']);
        $dbafs->method('shouldBeSynchronized')->willReturn(true);
        $dbafs->method('addResource')->willReturn(null);

        $framework = $this->mockContaoFramework([Dbafs::class => $dbafs]);
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case File::class:
                    $file = $this->mockClassWithProperties(File::class, []);
                    $file->method('getModel')->willReturn(null);

                    return $file;

                    break;

                default:
                    return null;

                    break;
            }
        });
        $container = System::getContainer();
        $container->set('contao.framework', $framework);
        System::setContainer($container);

        $this->assertSame(['error' => 'Speicherziel liegt außerhalb des Contao-Upload-Verzeichnisses.', 'filenameOrigEncoded' => 'dataTest.csv', 'filenameSanitized' => 'datatest.csv'], $function->invokeArgs($formMultiFileUpload, [$uploadFile, 'folder']));

        // create new file
        file_put_contents(UNIT_TESTING_FILES.'/dataTest.csv', 'test');

        // throwing invalid argument exception case
        $uploadFile = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/dataTest.csv', // Name of the sent file
            'dataTest.csv', // mime type
            'text/csv', // size of the file
            7006, null, true);

        $framework = $this->mockContaoFramework([Dbafs::class => $dbafs]);
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case File::class:
                    $filesModel = $this->mockClassWithProperties(FilesModel::class, ['isImage' => true, 'uuid' => 'uuid']);
                    $file = $this->mockClassWithProperties(File::class, ['isImage' => true, 'width' => 5]);
                    $file->method('getModel')->willReturn($filesModel);

                    return $file;

                    break;

                default:
                    return null;

                    break;
            }
        });
        $imageUtils = $this->mockAdapter(['getPixelValue']);
        $imageUtils->method('getPixelValue')->willReturn(10);

        $container = System::getContainer();
        $container->set('contao.framework', $framework);
        $container->set('huh.utils.image', $imageUtils);
        System::setContainer($container);

        $this->assertSame(['error' => 'Die Breite des Bildes darf 10 Pixel nicht unterschreiten (aktuelle Bildbreite: 5 Pixel).', 'filenameOrigEncoded' => 'dataTest.csv', 'filenameSanitized' => 'datatest.csv'], $function->invokeArgs($formMultiFileUpload, [$uploadFile, 'folder']));

        // create new file
        file_put_contents(UNIT_TESTING_FILES.'/dataTest.csv', 'test');

        // throwing invalid argument exception case
        $uploadFile = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/dataTest.csv', // Name of the sent file
            'dataTest.csv', // mime type
            'text/csv', // size of the file
            7006, null, true);

        $framework = $this->mockContaoFramework([Dbafs::class => $dbafs]);
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case File::class:
                    $filesModel = $this->mockClassWithProperties(FilesModel::class, ['isImage' => true, 'uuid' => 'uuid']);
                    $file = $this->mockClassWithProperties(File::class, ['isImage' => false, 'width' => 5]);
                    $file->method('getModel')->willReturn($filesModel);

                    return $file;

                    break;

                default:
                    return null;

                    break;
            }
        });
        $imageUtils = $this->mockAdapter(['getPixelValue']);
        $imageUtils->method('getPixelValue')->willReturn(10);

        $container = System::getContainer();
        $container->set('contao.framework', $framework);
        $container->set('huh.utils.image', $imageUtils);
        System::setContainer($container);

        $this->assertSame('dataTest.csv', $function->invokeArgs($formMultiFileUpload, [$uploadFile, 'folder'])['filenameOrigEncoded']);
    }

    /**
     * @return DataContainer| \PHPUnit_Framework_MockObject_MockObject
     */
    public function getDataContainer($activeRecord = null)
    {
        return $this->mockClassWithProperties(DataContainer::class, ['table' => 'tl_files', 'activeRecord' => $activeRecord, 'id' => 12]);
    }

    /**
     * callback function for testing.
     *
     * @param $file
     * @param $class
     */
    public function callbackFunction($file, $class)
    {
        return true;
    }

    protected function getMethod($class, $name)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
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
