<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests\Backend;

use Contao\Controller;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\File;
use Contao\PageModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Contao\Widget;
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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class MultiFileUploadTest extends ContaoTestCase
{
    public function setUp()
    {
        parent::setUp();

        global $objPage;

        $objPage = $this->mockClassWithProperties(PageModel::class, ['outputFormat' => 'html5']);

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
        $GLOBALS['TL_LANG']['MSC']['dropzone']['labels'] = ['labels'];
        $GLOBALS['TL_LANG']['MSC']['dropzone']['messages'] = [];
        $GLOBALS['TL_DCA']['tl_files']['config'] = [
            'onsubmit_callback' => ['files'],
            'dataContainer' => 'File',
        ];
        $GLOBALS['TL_DCA']['tl_files']['fields']['title'] = [];
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = 80;

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

        $requestStack = new RequestStack();
        $requestStack->push(new \Symfony\Component\HttpFoundation\Request());

        $container = $this->mockContainer($this->getTempDir());
        $container->set('huh.utils.url', new UrlUtil($this->mockContaoFramework()));
        $container->set('request_stack', $requestStack);
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
        $container->setParameter('contao.resources_paths', [$this->getTempDir()]);
        $container->setParameter('kernel.logs_dir', $this->getTempDir());
        $container->setParameter('huh.multifileupload.mimeThemeDefault', 'system/modules/multifileupload/assets/img/mimetypes/Numix-uTouch');
        $container->setParameter('huh.multifileupload.uploadTmp', 'files/tmp');
        $container->setParameter('huh.multifileupload.maxFilesDefault', '10');
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

    public function testMultiFileUploadInstantiation()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => $this->getTempDir().'/files/uploads/',
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
            'eval' => ['uploadFolder' => $this->getTempDir().'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
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
            'eval' => ['uploadFolder' => $this->getTempDir().'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
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

    public function testGetMaximumUploadSize()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => ['uploadFolder' => $this->getTempDir().'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
            'options_callback' => '',
            'options' => '',
            'isSubmitCallback' => true,
            'exclude' => true,
        ];
        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
        $class = new MultiFileUpload($arrAttributes);
        $function = $this->getMethod(MultiFileUpload::class, 'getMaximumUploadSize');
        $result = $function->invokeArgs($class, []);
        $this->assertSame(2048000, $result);
    }

    public function testGetInfoAction()
    {
        $arrDca = [
            'label' => 'label',
            'inputType' => 'multifileupload',
            'eval' => ['uploadFolder' => $this->getTempDir().'/files/uploads/', 'extensions' => 'csv', 'fieldType' => 'radio', 'submitOnChange' => false, 'onchange' => '', 'allowHtml' => false, 'rte' => '', 'preserveTags' => '', 'sql' => 'varchar(255)', 'encrypt' => false, 'labels' => ['head' => 'head', 'body' => 'body', 'foot' => 'foot']],
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

    protected function getMethod($class, $name)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
