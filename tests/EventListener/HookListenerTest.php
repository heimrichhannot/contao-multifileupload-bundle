<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\RequestToken;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Exception\AjaxExitException;
use HeimrichHannot\MultiFileUploadBundle\EventListener\HookListener;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class HookListenerTest extends ContaoTestCase
{
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

        $container = $this->getContainerMock();
        $class = new HookListener($this->getFrameworkMock(), $container);
        $this->assertFalse($class->executePostActionsHook('test', $this->getDataContainer()));

//        $container->get('session')->set('multifileupload_fields', ['tl_files' => ['field' => $arrDca]]);
//
//        try {
//            $class->executePostActionsHook('multifileupload_upload', $this->getDataContainer());
//            $this->expectException(AjaxExitException::class);
//        } catch (AjaxExitException $exception) {
//            $message = json_decode($exception->getMessage());
//            $this->assertSame('Bad Request', $message->message);
//        }
//
//        $ajaxActionManager = new AjaxActionManager();
//        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$ajaxActionManager->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
//        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
//        $objRequest->request->set('requestToken', \RequestToken::get());
//        $objRequest->request->set('files', []);
//
//        // simulate upload of php file hidden in an image file
//        $file = new UploadedFile(// Path to the file to send
//            TL_ROOT.'/files/data.csv', // Name of the sent file
//            'data.csv', // mime type
//            'text/csv', // size of the file
//            7006, null, true);
//
//        $objRequest->files->add(['files' => $file]);
//
//        $requestStack = new RequestStack();
//        $requestStack->push($objRequest);
//
//        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
//        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);
//
//        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);
//
//        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
//        $tokenAdapter->method('getToken')->willReturnSelf();
//        $tokenAdapter->method('getValue')->willReturn('token');
//
//        $modelAdapter = $this->mockAdapter(['find']);
//        $modelAdapter->method('find')->willReturn(null);
//
//        $arrAttributes = Widget::getAttributesFromDca($arrDca, 'files', null, 'title', 'tl_files');
//
//        $widgetAdapter = $this->mockAdapter(['getAttributesFromDca']);
//        $widgetAdapter->method('getAttributesFromDca')->willReturn($arrAttributes);
//
//        $controller = $this->mockAdapter(['replaceInsertTags']);
//        $controller->method('replaceInsertTags')->willReturnCallback(function ($string, $bln) {
//            return Controller::replaceInsertTags($string, $bln);
//        });
//
//        $framework = $this->mockContaoFramework([Widget::class => $widgetAdapter, Controller::class => $controller]);
//        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
//            switch ($class) {
//                case Database::class:
//                    $database = $this->mockAdapter(['fieldExists', 'listFields']);
//                    $database->method('fieldExists')->willReturn(true);
//                    $database->method('listFields')->willReturn([]);
//
//                    return $database;
//                case File::class:
//                    $fileModel = $this->mockClassWithProperties(FilesModel::class, ['uuid' => '4923hef8fh827fhf448f0438h']);
//                    $file = $this->mockClassWithProperties(File::class, ['getModel' => 'true']);
//                    $file->method('getModel')->willReturn($fileModel);
//
//                    return $file;
//                default:
//                    return null;
//            }
//        });
//
//        $request = new Request($framework, $requestStack, $scopeMatcher);
//        $request->setGet('file', '');
//        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
//        $request->request->set('requestToken', \RequestToken::get());
//        $request->request->set('files', []);
//        $request->setPost('field', 'field');
//
//        $filesModel = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/cmd_test.php', 'uuid' => 'fuuf4h3pfuh34f4uh4f444', 'filesize' => '1024']);
//        $file = $this->mockClassWithProperties(File::class, ['value' => 'value', 'name' => 'data']);
//        $file->method('getModel')->willReturn($filesModel);
//        $file->method('exists')->willReturn(true);
//
//        $fileUtils = $this->mockAdapter(['getFileFromUuid', 'sanitizeFileName', 'addUniqueIdToFilename']);
//        $fileUtils->method('getFileFromUuid')->willReturn($file);
//        $fileUtils->method('sanitizeFileName')->willReturnCallback(function ($filename) { return $filename; });
//        $fileUtils->method('addUniqueIdToFilename')->willReturnCallback(function ($name, $prefix) {
//            $file = new FileUtil($this->mockContaoFramework());
//
//            return $file->addUniqueIdToFilename($name, $prefix);
//        });
//        $_SERVER['SERVER_NAME'] = 'localhost';
//        $_SERVER['SERVER_PORT'] = 80;
//        $ajaxManager = $this->mockAdapter(['runActiveAction']);
//
//        $container = System::getContainer();
//        $container->set('huh.request', $request);
//        $container->set('contao.framework', $framework);
//        $container->set('huh.utils.file', $fileUtils);
//        $container->set('huh.ajax.action', $ajaxActionManager);
//        $container->set('huh.utils.class', new ClassUtil());
//        $container->set('huh.ajax', $ajaxManager);
//        System::setContainer($container);
//
//        try {
//            $user = BackendUser::getInstance();
//            $user->admin = true;
//            $class->executePostActionsHook('multifileupload_upload', $this->getDataContainer());
//            $this->expectException(AjaxExitException::class);
//        } catch (AjaxExitException $exception) {
//            $message = json_decode($exception->getMessage());
//            $this->assertSame(200, $message->statusCode);
//        }
//
//        System::getContainer()->get('huh.request')->files->remove('files');
//        try {
//            $this->assertNull($class->executePostActionsHook('multifileupload_upload', $this->getDataContainer()));
//        } catch (AjaxExitException $exception) {
//            $message = json_decode($exception->getMessage());
//            $this->assertSame('Invalid Request. File not found in \Symfony\Component\HttpFoundation\FileBag', $message->message);
//        }
    }

    public function getContainerMock()
    {
        $container = $this->mockContainer();

        $tokenManager = $this->mockAdapter(['getToken', 'getValue', 'isTokenValid']);
        $tokenManager->method('getToken')->willReturnSelf();
        $tokenManager->method('getValue')->willReturn('token');
        $tokenManager->method('isTokenValid')->willReturn(true);

        $container->set('session', new Session(new MockArraySessionStorage()));
        $container->set('contao.csrf.token_manager', $tokenManager);
        $container->set('security.csrf.token_manager', $tokenManager);

        $backendMatcher = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);
        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);
        $requestStack = new RequestStack();
        $requestStack->push(new \Symfony\Component\HttpFoundation\Request());
        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
        $request->setGet('file', '');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $request->request->set('requestToken', RequestToken::get());
        $request->request->set('files', []);
        $container->set('huh.request', $request);

        System::setContainer($container);

        return $container;
    }

    public function getFrameworkMock()
    {
        $database = $this->mockAdapter(['fieldExists', 'listFields']);
        $database->method('fieldExists')->willReturn(true);
        $database->method('listFields')->willReturn([]);

        $controller = $this->mockAdapter(['replaceInsertTags']);
        $controller->method('replaceInsertTags')->willReturnCallback(function ($string, $bln) {
            return Controller::replaceInsertTags($string, $bln);
        });

        $framework = $this->mockContaoFramework([Controller::class => $controller]);
        $framework->method('createInstance')->willReturn($database);

        return $framework;
    }

    /**
     * @return DataContainer| \PHPUnit_Framework_MockObject_MockObject
     */
    public function getDataContainer($activeRecord = null)
    {
        return $this->mockClassWithProperties(DataContainer::class, ['table' => 'tl_files', 'activeRecord' => $activeRecord, 'id' => 12]);
    }
}
