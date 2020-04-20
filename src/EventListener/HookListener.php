<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\DataContainer;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Response\Response;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Response\DropzoneErrorResponse;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HookListener
{
    /**
     * @var ContaoFrameworkInterface
     */
    protected $framework;
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * HookListener constructor.
     */
    public function __construct(ContaoFrameworkInterface $framework, ContainerInterface $container)
    {
        $this->framework = $framework;
        $this->container = $container;
    }

    public function executePostActionsHook(string $action, DataContainer $dc)
    {
        if (MultiFileUpload::ACTION_UPLOAD_BACKEND !== $action) {
            return false;
        }
        $request = $this->container->get('huh.request');

        $fields = $this->container->get('session')->get(MultiFileUpload::SESSION_FIELD_KEY);

        // Check whether the field is allowed for regular users
        if (!isset($fields[$dc->table][$request->getPost('field')]) || (!isset($fields[$dc->table]['fields'][$request->getPost('field')]['exclude']) && !BackendUser::getInstance()->hasAccess($dc->table.'::'.$request->getPost('field'), 'alexf'))) {
            $this->container->get('monolog.logger.contao')->log(
                LogLevel::ERROR,
                'Field "'.$request->getPost('field').'" is not an allowed selector field (possible SQL injection attempt)',
                ['contao' => new ContaoContext(__CLASS__.'::'.__METHOD__, TL_ERROR)]
            );

            $objResponse = new DropzoneErrorResponse();
            $objResponse->setMessage('Bad Request');
            $objResponse->output();
        }

        if (null === $dc->activeRecord) {
            $dc->activeRecord = $this->container->get('huh.utils.model')->findModelInstanceByPk($dc->table, $dc->id);
        }

        // add dca attributes and instantiate current object to set widget attributes
        $attributes = $this->framework->getAdapter(Widget::class)->getAttributesFromDca($fields[$dc->table][$request->getPost('field')], $request->getPost('field'));
        $objUploader = new FormMultiFileUpload($attributes);
        $objResponse = $objUploader->upload();

        /* @var Response */
        if ($objResponse instanceof Response) {
            $objResponse->output();
        }
    }
}
