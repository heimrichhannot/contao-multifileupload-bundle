<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

// * Copyright (c) 2018 Heimrich & Hannot GmbH
// *
// * @license LGPL-3.0-or-later
// */
//
//namespace HeimrichHannot\MultiFileUploadBundle\Tests\Form;
//
//
//use Contao\CoreBundle\Routing\ScopeMatcher;
//use Contao\PageModel;
//use Contao\System;
//use Contao\TestCase\ContaoTestCase;
//use HeimrichHannot\AjaxBundle\Manager\AjaxManager;
//use HeimrichHannot\MultiFileUpload\Form\FormMultiFileUpload;
//use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
//use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
//use Symfony\Component\HttpFoundation\RequestMatcher;
//use Symfony\Component\HttpFoundation\RequestStack;
//
//class FormMultiFileUploadTest extends ContaoTestCase
//{
//
//    public function setUp()
//    {
//        parent::setUp();
//
//        if (!defined('TL_MODE')) {
//            define('TL_MODE', 'FE');
//        }
//
//        $GLOBALS['TL_DCA']['tl_file']['config']['onsubmit_callback'] = '';
//
//        global $objPage;
//
//        $objPage = $this->mockClassWithProperties(PageModel::class, ['outputFormat' => 'no']);
//
//        $database = $this->mockAdapter(['fieldExists', 'listFields']);
//        $database->method('fieldExists')->willReturn(true);
//        $database->method('listFields')->willReturn([]);
//
//        $framework = $this->mockContaoFramework();
//        $framework->method('createInstance')->willReturn($database);
//
//        $databaseConnection = $this->mockAdapter([]);
//
//        $requestStack = new RequestStack();
//        $requestStack->push(new \Symfony\Component\HttpFoundation\Request());
//
//        $backendMatcher  = new RequestMatcher('/contao', 'test.com', null, ['192.168.1.0']);
//        $frontendMatcher = new RequestMatcher('/index', 'test.com', null, ['192.168.1.0']);
//
//        $scopeMatcher = new ScopeMatcher($backendMatcher, $frontendMatcher);
//
//        $tokenAdapter = $this->mockAdapter(['getToken', 'getValue']);
//        $tokenAdapter->method('getToken')->willReturnSelf();
//        $tokenAdapter->method('getValue')->willReturn('token');
//
//        $request = new Request($this->mockContaoFramework(), $requestStack, $scopeMatcher);
//        $request->setGet('file', '');
//        $request->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
//
//        $container = $this->mockContainer();
//        $container->set('contao.framework', $framework);
//        $container->set('huh.utils.container', new ContainerUtil($this->mockContaoFramework()));
//        $container->set('huh.ajax', new AjaxManager());
//        $container->set('request_stack', $requestStack);
//        $container->set('database_connection', $databaseConnection);
//        $container->set('huh.request', $request);
//        System::setContainer($container);
//
//        if (!\interface_exists('uploadable')) {
//            include_once __DIR__ . '/../../vendor/contao/core-bundle/src/Resources/contao/helper/interface.php';
//        }
//    }
//
//    public function testInstantiation()
//    {
//        $form = new FormMultiFileUpload(['strTable' => 'tl_file', 'isSubmitCallback' => true, 'name' => 'test', 'fieldType' => 'inputType']);
//        $this->assertInstanceOf(FormMultiFileUpload::class, $form);
//    }
//}
