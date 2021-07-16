<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Response\Response;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Response\DropzoneErrorResponse;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @Hook("executePostActions")
 */
class ExecutePostActionsListener
{
    /** @var Request */
    protected $request;

    /** @var Session */
    protected $session;

    /** @var LoggerInterface */
    protected $logger;

    /** @var ModelUtil */
    protected $modelUtil;

    /** @var ContaoFramework */
    protected $framework;

    /**
     * ExecutePostActionsListener constructor.
     */
    public function __construct(Request $request, Session $session, LoggerInterface $logger, ModelUtil $modelUtil, ContaoFramework $framework)
    {
        $this->request = $request;
        $this->session = $session;
        $this->logger = $logger;
        $this->modelUtil = $modelUtil;
        $this->framework = $framework;
    }

    public function __invoke(string $action, DataContainer $dc): void
    {
        if (MultiFileUpload::ACTION_UPLOAD_BACKEND !== $action) {
            return;
        }

        $fields = $this->session->get(MultiFileUpload::SESSION_FIELD_KEY);

        // Check whether the field is allowed for regular users
        if (!isset($fields[$dc->table][$this->request->getPost('field')]) || (!isset($fields[$dc->table]['fields'][$this->request->getPost('field')]['exclude']) && !BackendUser::getInstance()->hasAccess($dc->table.'::'.$this->request->getPost('field'), 'alexf'))) {
            $this->logger->log(
                LogLevel::ERROR,
                'Field "'.$this->request->getPost('field').'" is not an allowed selector field (possible SQL injection attempt)',
                ['contao' => new ContaoContext(__CLASS__.'::'.__METHOD__, TL_ERROR)]
            );

            $objResponse = new DropzoneErrorResponse();
            $objResponse->setMessage('Bad Request');
            $objResponse->output();
        }

        if (null === $dc->activeRecord) {
            $dc->activeRecord = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);
        }

        // add dca attributes and instantiate current object to set widget attributes
        $attributes = $this->framework->getAdapter(Widget::class)->getAttributesFromDca($fields[$dc->table][$this->request->getPost('field')], $this->request->getPost('field'));
        $objUploader = new FormMultiFileUpload($attributes);
        $objResponse = $objUploader->upload();

        /* @var Response */
        if ($objResponse instanceof Response) {
            $objResponse->output();
        }
    }
}
