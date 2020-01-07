<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests\Backend;

use Contao\Controller;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\File;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\RequestToken;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Manager\AjaxActionManager;
use HeimrichHannot\AjaxBundle\Manager\AjaxManager;
use HeimrichHannot\AjaxBundle\Manager\AjaxTokenManager;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Arrays\ArrayUtil;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\File\FileUtil;
use HeimrichHannot\UtilsBundle\String\StringUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class MultiFileUploadTest extends ContaoTestCase
{
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
        global $objPage;

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

        $GLOBALS['TL_DCA']['tl_files']['fields']['title'] = [];

        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = 80;

        $requestStack = new RequestStack();
        $requestStack->push(new \Symfony\Component\HttpFoundation\Request());

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);

        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);

        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
        $tokenAdapter->method('getToken')->willReturnSelf();
        $tokenAdapter->method('getValue')->willReturn('token');

        $this->request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
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

        $container = $this->mockContainer(TL_ROOT);
        $container->set('huh.utils.url', new UrlUtil($this->mockContaoFramework()));
        $container->set('request_stack', $requestStack);
        $container->set('contao.csrf.token_manager', $tokenManager);
        $container->set('security.csrf.token_manager', $tokenManager);
        $container->set('contao.framework', $framework);
        $container->set('session', new Session(new MockArraySessionStorage()));
        $container->set('database_connection', $this->mockClassWithProperties(Database::class, []));
        $filelocator = new FileLocator($this->createMock(KernelInterface::class));
        $container->set('huh.utils.container', new ContainerUtil($this->mockContaoFramework(), $filelocator, $scopeMatcher));
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
        $request->request->set('requestToken', RequestToken::get());
        $request->request->set('files', []);

        $container = System::getContainer();
        $container->set('huh.request', $request);
        $container->set('huh.ajax.token', new AjaxTokenManager());
        System::setContainer($container);

        if (!interface_exists('uploadable')) {
            include_once __DIR__.'/../../vendor/contao/core-bundle/src/Resources/contao/helper/interface.php';
        }
    }

    public function testMultiFileUploadInstantiation()
    {
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

        $class = new MultiFileUpload($arrAttributes);
        $this->assertInstanceOf(MultiFileUpload::class, $class);

        $controller = $this->mockAdapter(['sendFileToBrowser']);
        $controller->method('sendFileToBrowser')->willThrowException(new \Exception('sendFileToBrowser'));

        System::getContainer()->get('huh.request')->setGet('file', 'file');
        System::getContainer()->get('session')->set('multifileupload_allowed_downloads', ['file']);
        $container = System::getContainer();
        $container->set('contao.framework', $this->mockContaoFramework([Controller::class => $controller]));
        System::setContainer($container);

        try {
            $class = new MultiFileUpload($arrAttributes);
        } catch (\Exception $exception) {
            $this->assertSame('sendFileToBrowser', $exception->getMessage());
        }
    }

    public function testGenerateMarkup()
    {
        $file = $this->mockAdapter(['getPathname']);
        $file->method('getPathname')->willReturn(__DIR__.'/../../src/Resources/contao/templates/forms/form_multifileupload_dropzone.html5');

        $resourceFinder = $this->mockAdapter(['findIn', 'name']);
        $resourceFinder->method('findIn')->willReturnSelf();
        $resourceFinder->method('name')->willReturnCallback(function ($fileName) {
            $file = $this->mockAdapter(['getPathname']);

            switch ($fileName) {
                case 'form_multifileupload_dropzone.html5':
                    $file->method('getPathname')->willReturn(__DIR__.'/../../src/Resources/contao/templates/forms/form_multifileupload_dropzone.html5');

                    return [$file];

                    break;

                case 'form_row.html5':
                    $file->method('getPathname')->willReturn(__DIR__.'/../../vendor/contao/core-bundle/src/Resources/contao/templates/forms/form_row.html5');

                    return [$file];

                    break;
            }
        });

        $container = System::getContainer();
        $container->set('contao.resource_finder', $resourceFinder);
        $container->setParameter('kernel.bundles', []);
        System::setContainer($container);

        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => ['uploadFolder' => TL_ROOT.'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $backendMultiFileUpload = new BackendMultiFileUpload($arrAttributes);

        $class = new MultiFileUpload($arrAttributes, $backendMultiFileUpload);

        $template = "
<div class=\"\">
      <label style=\"font-weight: bold;\" for=\"ctrl_files\">
                    label            </label>

  <div  data-onchange=\"this.form.submit()\"  data-param-name=\"files\"  data-max-filesize=\"1.95\"  data-accepted-files=\".csv\"  data-thumbnail-width=\"90\"  data-thumbnail-height=\"90\"  data-create-image-thumbnails=\"true\"  data-request-token=\"token\"  data-previews-container=\"#ctrl_files .dropzone-previews\"  data-upload-multiple=\"\"  data-max-files=\"1\" class=\"multifileupload dropzone\" id=\"ctrl_files\">
    <input type=\"hidden\" name=\"formattedInitial_files\" value='[]'>
    <input type=\"hidden\" name=\"uploaded_files\" value='[]'>
    <input type=\"hidden\" name=\"deleted_files\" value='[]'>
    <input type=\"hidden\" name=\"files\" value='[]'>
    <div class=\"fallback\">
        <input type=\"file\" name=\"files\">
    </div>
    <div class=\"dz-container\">
        <div class=\"dz-default dz-message\">
            <span class=\"dz-message-head\">head</span>
            <span class=\"dz-message-body\">body</span>
            <span class=\"dz-message-foot\">foot</span>
        </div>
        <div class=\"dropzone-previews\"></div>
    </div>
</div>
        
</div>
";

        $this->assertSame($template, $class->generateMarkup());
    }

    public function testGetDropZoneOptions()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => ['uploadFolder' => TL_ROOT.'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $backendMultiFileUpload = new BackendMultiFileUpload($arrAttributes);
        $arrAttributes['dictMaxFilesExceeded'] = true;

        $class = new MultiFileUpload($arrAttributes, $backendMultiFileUpload);
        $function = $this->getMethod(MultiFileUpload::class, 'getDropZoneOptions');
        $result = $function->invokeArgs($class, []);
        $this->assertSame([
            'data-onchange' => 'this.form.submit()',
            'data-param-name' => 'files',
            'data-dict-max-files-exceeded' => true,
            'data-max-filesize' => 1.95,
            'data-accepted-files' => '.csv',
            'data-thumbnail-width' => 90,
            'data-thumbnail-height' => 90,
            'data-create-image-thumbnails' => 'true',
            'data-request-token' => 'token',
            'data-previews-container' => '#ctrl_files .dropzone-previews',
            'data-upload-multiple' => false,
            'data-max-files' => 1,
        ], $result);

        $array = ['dictMaxFilesExceeded'];

        foreach ($array as $item) {
            $this->assertTrue($class->getDropZoneOption($item));
        }
    }

    public function testGetMaximumUploadFileSize()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => ['uploadFolder' => TL_ROOT.'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $class = new MultiFileUpload($arrAttributes);
        $function = $this->getMethod(MultiFileUpload::class, 'getMaximumUploadFileSize');
        $result = $function->invokeArgs($class, []);
        $this->assertSame(2048000, $result);
    }

    public function testGetInfoAction()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => ['uploadFolder' => TL_ROOT.'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $class = new MultiFileUpload($arrAttributes);
        $file = $this->mockClassWithProperties(File::class, ['value' => 'value']);

        $containerUtils = $this->mockAdapter(['isFrontend', 'isBackend']);
        $containerUtils->method('isFrontend')->willReturn(null);
        $containerUtils->method('isBackend')->willReturn(null);

        $ajaxAction = $this->mockAdapter(['removeAjaxParametersFromUrl']);
        $ajaxAction->method('removeAjaxParametersFromUrl')->willReturn('href');

        $router = $this->mockAdapter(['generate']);
        $router->method('generate')->willReturn('href');

        $container = System::getContainer();
        $container->set('huh.utils.container', $containerUtils);
        $container->set('huh.ajax.action', $ajaxAction);
        $container->set('router', $router);
        System::setContainer($container);

        $function = $this->getMethod(MultiFileUpload::class, 'getInfoAction');
        $result = $function->invokeArgs($class, [$file]);
        $this->assertNull($result);

        $containerUtils = $this->mockAdapter(['isFrontend', 'isBackend']);
        $containerUtils->method('isFrontend')->willReturn(true);
        $containerUtils->method('isBackend')->willReturn(null);

        $container = System::getContainer();
        $container->set('huh.utils.container', $containerUtils);
        System::setContainer($container);

        $function = $this->getMethod(MultiFileUpload::class, 'getInfoAction');
        $result = $function->invokeArgs($class, [$file]);
        $this->assertSame('window.open("href?file=value", "_blank");', $result);

        $containerUtils = $this->mockAdapter(['isFrontend', 'isBackend']);
        $containerUtils->method('isFrontend')->willReturn(false);
        $containerUtils->method('isBackend')->willReturn(true);

        $container = System::getContainer();
        $container->set('huh.utils.container', $containerUtils);
        System::setContainer($container);

        $function = $this->getMethod(MultiFileUpload::class, 'getInfoAction');
        $result = $function->invokeArgs($class, [$file]);
        $this->assertSame('Backend.openModalIframe({"width":"664","title":"","url":"href","height":"299"});', $result);
    }

    public function testGePreviewImage()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => ['uploadFolder' => TL_ROOT.'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $file = $this->mockClassWithProperties(File::class, ['isImage' => false, 'extension' => 'extension']);

        $class = new MultiFileUpload($arrAttributes);
        $function = $this->getMethod(MultiFileUpload::class, 'getPreviewImage');
        $this->assertNull($function->invokeArgs($class, [$file]));

        $container = System::getContainer();
        $container->setParameter('huh.multifileupload.mime_theme_default', 'files');
        System::setContainer($container);

        @copy(__DIR__.'/../../src/Resources/public/img/mimetypes/Numix-uTouch/mimetypes.json', TL_ROOT.'/files/mimetypes.json');
        $this->assertNull($function->invokeArgs($class, [$file]));

        $file = $this->mockClassWithProperties(File::class, ['isImage' => false, 'extension' => 'csv']);
        $this->assertNull($function->invokeArgs($class, [$file]));

        copy(__DIR__.'/../../src/Resources/public/img/mimetypes/Numix-uTouch/application-database.png', TL_ROOT.'/files/application-database.png');
        $this->assertSame('files/application-database.png', $function->invokeArgs($class, [$file]));

        $file = $this->mockClassWithProperties(File::class, ['isImage' => true, 'extension' => 'csv', 'path' => 'files/application-database.png']);
        $this->assertSame('files/application-database.png', $function->invokeArgs($class, [$file]));
    }

    public function testPrepareValue()
    {
        $fileModel = $this->mockClassWithProperties(FilesModel::class, ['uuid' => 'dfd1cc5e-2c49-11e8-b467-0ed5f89f718b']);

        $file = $this->mockClassWithProperties(File::class, ['filesize' => 1024, 'name' => 'name', 'isImage' => true, 'path' => 'path', 'value' => 'value']);
        $file->method('getModel')->willReturn($fileModel);
        $file->method('exists')->willReturn(true);

        $fileUtils = $this->mockAdapter(['getFileFromUuid']);
        $fileUtils->method('getFileFromUuid')->willReturn($file);

        $container = System::getContainer();
        $container->set('huh.utils.file', $fileUtils);
        System::setContainer($container);

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
                'rgxp' => '',
                'encrypt' => false,
                'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot'],
            ],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', ['value'], 'title', 'tl_files');
        $class = new MultiFileUpload($arrAttributes);

        $function = $this->getMethod(MultiFileUpload::class, 'prepareValue');
        $result = $function->invoke($class);
        $this->assertSame('[{"name":"name","uuid":"64666431-6363-3565-2d32-6334392d3131","size":1024,"dataURL":"path"}]', $result);
    }

    public function testGeBytes()
    {
        $class = $this->getMockBuilder(MultiFileUpload::class)->setMethods(['getByteSize'])->disableOriginalConstructor()->getMock();
        $function = $this->getMethod(MultiFileUpload::class, 'getByteSize');
        $this->assertSame(1024.0, $function->invokeArgs($class, ['1KB']));
        $this->assertSame(1048576.0, $function->invokeArgs($class, ['1MB']));
        $this->assertSame(1073741824.0, $function->invokeArgs($class, ['1GB']));
    }

    protected function getMethod($class, $name)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
