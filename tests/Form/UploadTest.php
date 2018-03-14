<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests\Form;

use Contao\Controller;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\AjaxBundle\Exception\AjaxExitException;
use HeimrichHannot\AjaxBundle\Manager\AjaxActionManager;
use HeimrichHannot\AjaxBundle\Manager\AjaxManager;
use HeimrichHannot\Haste\Util\Url;
use HeimrichHannot\MultiFileUpload\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUpload\Form\FormMultiFileUpload;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

class UploadTest extends ContaoTestCase
{
    /**
     * @var Request
     */
    protected $request;

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
        parent::setUp();

        if (!defined('TL_MODE')) {
            define('TL_MODE', 'FE');
        }

        if (!defined('UNIT_TESTING')) {
            define('UNIT_TESTING', true);
        }

        if (!defined('UNIT_TESTING_FILES')) {
            define('UNIT_TESTING_FILES', $this->getTempDir().'/files');
        }

        $fs = new Filesystem();
        $fs->mkdir($this->getTempDir().'/files');
        $fs->mkdir($this->getTempDir().'/files/tmp');
        $fs->mkdir($this->getTempDir().'/files/uploads');

        $files = [
            'საბეჭდი_მანქანა.png',
            '.~file   name#%&*{}:<>?+|".zip',
            'file___name.zip',
            'file...name.zip',
            'file name.zip',
            'file--.--.-.--name.zip',
            'file---name.zip',
        ];

        $this->createTestFiles($files);

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

        $container = $this->mockContainer();
        $container->set('huh.utils.url', new UrlUtil($this->mockContaoFramework()));
        System::setContainer($container);
    }

    /**
     * test upload controller against cross-site request.
     *
     * @test
     */
    public function testUploadHTMLInjection()
    {
        $strAction = $this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD);

        $objRequest = $this->request->create('http://localhost'.$strAction, 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        @copy(UNIT_TESTING_FILES.'file   name.zip', UNIT_TESTING_FILES.'/tmp/file   name.zip');

        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file   name.zip', // Name of the sent file
                '"b<marquee onscroll=alert(1)>file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles[0]]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'zip',
                'fieldType' => 'radio',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

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
        $strAction = Url::removeQueryString([AjaxManager::AJAX_ATTR_TOKEN], $strAction);
        $strAction = Url::addQueryString(AjaxManager::AJAX_ATTR_TOKEN.'='. 12355456, $strAction);

        $objRequest = $this->request->create('http://localhost'.$strAction, 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        @copy(UNIT_TESTING_FILES.'/file   name.zip', UNIT_TESTING_FILES.'/tmp/file   name.zip');
        @copy(UNIT_TESTING_FILES.'/file---name.zip', UNIT_TESTING_FILES.'/tmp/file---name.zip');
        @copy(UNIT_TESTING_FILES.'/file--.--.-.--name.zip', UNIT_TESTING_FILES.'/tmp/file--.--.-.--name.zip');
        @copy(UNIT_TESTING_FILES.'/file...name..zip', UNIT_TESTING_FILES.'/tmp/file...name..zip');
        @copy(UNIT_TESTING_FILES.'/file___name.zip', UNIT_TESTING_FILES.'/tmp/file___name.zip');
        @copy(UNIT_TESTING_FILES.'/.~file   name#%&*{}:<>?+|"\'.zip', UNIT_TESTING_FILES.'/tmp/.~file   name#%&*{}:<>?+|"\'.zip');

        // simulate upload of php file hidden in an image file
        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file   name.zip', // Name of the sent file
                'file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file---name.zip', // Name of the sent file
                'file---name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file--.--.-.--name.zip', // Name of the sent file
                'file--.--.-.--name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file...name..zip', // Name of the sent file
                'file...name..zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file___name.zip', // Name of the sent file
                'file___name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/.~file   name#%&*{}:<>?+|"\'.zip', // Name of the sent file
                '.~file   name#%&*{}:<>?+|"\'.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'zip',
                'maxFiles' => 2,
                'fieldType' => 'checkbox',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
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

        @copy(UNIT_TESTING_FILES.'/file   name.zip', UNIT_TESTING_FILES.'/tmp/file   name.zip');
        @copy(UNIT_TESTING_FILES.'/file---name.zip', UNIT_TESTING_FILES.'/tmp/file---name.zip');
        @copy(UNIT_TESTING_FILES.'/file--.--.-.--name.zip', UNIT_TESTING_FILES.'/tmp/file--.--.-.--name.zip');
        @copy(UNIT_TESTING_FILES.'/file...name..zip', UNIT_TESTING_FILES.'/tmp/file...name..zip');
        @copy(UNIT_TESTING_FILES.'/file___name.zip', UNIT_TESTING_FILES.'/tmp/file___name.zip');
        @copy(UNIT_TESTING_FILES.'/.~file   name#%&*{}:<>?+|"\'.zip', UNIT_TESTING_FILES.'/tmp/.~file   name#%&*{}:<>?+|"\'.zip');

        // simulate upload of php file hidden in an image file
        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file   name.zip', // Name of the sent file
                'file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file---name.zip', // Name of the sent file
                'file---name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file--.--.-.--name.zip', // Name of the sent file
                'file--.--.-.--name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file...name..zip', // Name of the sent file
                'file...name..zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file___name.zip', // Name of the sent file
                'file___name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/.~file   name#%&*{}:<>?+|"\'.zip', // Name of the sent file
                '.~file   name#%&*{}:<>?+|"\'.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'zip',
                'maxFiles' => 2,
                'fieldType' => 'checkbox',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

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

        // prevent test file removal
        @copy(UNIT_TESTING_FILES.'/file   name.zip', UNIT_TESTING_FILES.'/tmp/file   name.zip');
        @copy(UNIT_TESTING_FILES.'/file---name.zip', UNIT_TESTING_FILES.'/tmp/file---name.zip');
        @copy(UNIT_TESTING_FILES.'/file--.--.-.--name.zip', UNIT_TESTING_FILES.'/tmp/file--.--.-.--name.zip');
        @copy(UNIT_TESTING_FILES.'/file...name..zip', UNIT_TESTING_FILES.'/tmp/file...name..zip');
        @copy(UNIT_TESTING_FILES.'/file___name.zip', UNIT_TESTING_FILES.'/tmp/file___name.zip');
        @copy(UNIT_TESTING_FILES.'/.~file   name#%&*{}:<>?+|"\'.zip', UNIT_TESTING_FILES.'/tmp/.~file   name#%&*{}:<>?+|"\'.zip');

        // simulate upload of php file hidden in an image file
        $arrFiles = [
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file   name.zip', // Name of the sent file
                'file   name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file---name.zip', // Name of the sent file
                'file---name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file--.--.-.--name.zip', // Name of the sent file
                'file--.--.-.--name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file...name..zip', // Name of the sent file
                'file...name..zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/file___name.zip', // Name of the sent file
                'file___name.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
            new UploadedFile(// Path to the file to send
                UNIT_TESTING_FILES.'/tmp/.~file   name#%&*{}:<>?+|"\'.zip', // Name of the sent file
                '.~file   name#%&*{}:<>?+|"\'.zip', // mime type
                'application/zip', // size of the file
                48140, null, true),
        ];

        $objRequest->files->add(['files' => $arrFiles]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'zip',
                'fieldType' => 'checkbox',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

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
        $objRequest = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$this->ajaxAction->generateUrl(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD), 'post');
        $objRequest->headers->set('X-Requested-With', 'XMLHttpRequest'); // xhr request
        $objRequest->request->set('requestToken', \RequestToken::get());
        $objRequest->request->set('files', []);

        // prevent test file removal
        @copy(UNIT_TESTING_FILES.'/საბეჭდი_მანქანა.png', UNIT_TESTING_FILES.'/tmp/საბეჭდი_მანქანა.png');

        // simulate upload of php file hidden in an image file
        $file = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/tmp/საბეჭდი_მანქანა.png', // Name of the sent file
            'საბეჭდი_მანქანა.png', // mime type
            'image/png', // size of the file
            64693, null, true);

        $objRequest->files->add(['files' => $file]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'jpg,jpeg,gif,png',
                'fieldType' => 'radio',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            $objUploader->upload();
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertSame('sabejdi_mankhana.png', $objJson->result->data->filenameSanitized);
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

        // prevent test file removal
        @copy(UNIT_TESTING_FILES.'/data.csv', UNIT_TESTING_FILES.'/tmp/data.csv');

        // simulate upload of php file hidden in an image file
        $file = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/tmp/data.csv', // Name of the sent file
            'data.csv', // mime type
            'text/csv', // size of the file
            7006, null, true);

        $objRequest->files->add(['files' => $file]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'csv',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

        try {
            $objUploader = new FormMultiFileUpload($arrAttributes);
            // unreachable code: if no exception is thrown after form was created, something went wrong
            $this->expectException(AjaxExitException::class);
        } catch (AjaxExitException $e) {
            $objJson = json_decode($e->getMessage());

            $this->assertNull($objJson->result->data->error);
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

        // prevent test file removal
        @copy(UNIT_TESTING_FILES.'/cmd_test.php.jpg', UNIT_TESTING_FILES.'/tmp/cmd_test.php.jpg');

        // simulate upload of php file hidden in an image file
        $file = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/tmp/cmd_test.php.jpg', // Name of the sent file
            'cmd_test.php.jpg', // mime type
            'image/jpeg', // size of the file
            652, null, true);

        $objRequest->files->add(['files' => $file]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'jpg,jpeg,gif,png',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

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
        @copy(UNIT_TESTING_FILES.'/cmd_test.php.jpg', UNIT_TESTING_FILES.'/tmp/cmd_test.php.jpg');
        @copy(UNIT_TESTING_FILES.'/cmd_test.php', UNIT_TESTING_FILES.'/tmp/cmd_test.php');
        @copy(UNIT_TESTING_FILES.'/cmd_test1.php', UNIT_TESTING_FILES.'/tmp/cmd_test1.php');

        $file = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/tmp/cmd_test.php', // Name of the sent file
            'cmd_test.php', // mime type
            'text/x-php', // size of the file
            652, null, true);

        $file2 = new UploadedFile(// Path to the file to send
            UNIT_TESTING_FILES.'/tmp/cmd_test1.php', // Name of the sent file
            'cmd_test1.php', // mime type
            'text/x-php', // size of the file
            652, null, true);

        $objRequest->files->add(['files' => [$file, $file2]]);

//        Request::set($objRequest);

        $arrDca = [
            'inputType' => 'multifileupload',
            'eval' => [
                'uploadFolder' => UNIT_TESTING_FILES.'uploads/',
                'extensions' => 'jpg,jpeg,gif,png',
                'fieldType' => 'checkbox',
            ],
        ];

        $arrAttributes = \Widget::getAttributesFromDca($arrDca, 'files');

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
