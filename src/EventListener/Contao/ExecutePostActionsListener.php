<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener\Contao;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Response\Response;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Response\DropzoneErrorResponse;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * @Hook("executePostActions")
 */
class ExecutePostActionsListener
{
    private RequestStack     $requestStack;
    private SessionInterface $session;
    private Security         $security;
    private LoggerInterface  $logger;
    private Utils            $utils;
    private ContaoFramework  $contaoFramework;

    public function __construct(RequestStack $requestStack, SessionInterface $session, Security $security, LoggerInterface $logger, Utils $utils, ContaoFramework $contaoFramework)
    {
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->security = $security;
        $this->logger = $logger;
        $this->utils = $utils;
        $this->contaoFramework = $contaoFramework;
    }

    public function __invoke(string $action, DataContainer $dc): void
    {
        if (MultiFileUpload::ACTION_UPLOAD_BACKEND !== $action) {
            return;
        }

        $fields = $this->session->get(MultiFileUpload::SESSION_FIELD_KEY);

        if (!($request = $this->requestStack->getCurrentRequest())) {
            return;
        }

        $currentField = $request->request->get('field');

        // Check whether the field is allowed for regular users
        if (
            !$currentField
            || !isset($fields[$dc->table][$currentField])
            || (
                !isset($fields[$dc->table]['fields'][$currentField]['exclude'])
                && !$this->security->getUser()->hasAccess($dc->table.'::'.$currentField, 'alexf')
            )) {
            $this->logger->log(
                LogLevel::ERROR,
                'Field "'.$request->getPost('field').'" is not an allowed selector field (possible SQL injection attempt)',
                ['contao' => new ContaoContext(__CLASS__.'::'.__METHOD__, TL_ERROR)]
            );

            $objResponse = new DropzoneErrorResponse('Bad Request');
        }

        if (null === $dc->activeRecord) {
            $dc->activeRecord = $this->utils->model()->findModelInstanceByPk($dc->table, $dc->id);
        }

        // add dca attributes and instantiate current object to set widget attributes
        $attributes = $this->contaoFramework->getAdapter(Widget::class)->getAttributesFromDca($fields[$dc->table][$currentField], $currentField);
        $objUploader = new FormMultiFileUpload($attributes);
//        $objResponse = $objUploader->upload();

        /* @var Response $objResponse */
        if ($objResponse instanceof Response) {
            $objResponse->output();
        }
    }
}
