<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticFormActionsBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\FormBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticFormActionsBundle\Entity\FormActions;
use MauticPlugin\MauticFormActionsBundle\Entity\FormActionsLog;
use MauticPlugin\MauticFormActionsBundle\Form\Type\FormActionSaveField;
use MauticPlugin\MauticFormActionsBundle\FormActionsEvents;
use MauticPlugin\MauticFormActionsBundle\Integration\FormActionsSettings;
use MauticPlugin\MauticFormActionsBundle\Model\FormActionsModel;
use MauticPlugin\MauticFormActionsBundle\Trigger\TriggerFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;
use Twig\TwigFilter;

class FormSubscriber implements EventSubscriberInterface
{
    /**
     * @var FormActionsSettings
     */
    private $formActionsSettings;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var FieldModel
     */
    private $fieldModel;

    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * LeadSubscriber constructor.
     *
     * @param FormActionsSettings $formActionsSettings
     * @param Environment   $twig
     * @param FieldModel          $fieldModel
     * @param LeadModel           $leadModel
     */
    public function __construct(
        FormActionsSettings $formActionsSettings,
        Environment $twig,
        FieldModel $fieldModel,
        LeadModel $leadModel
    ) {
        $this->twig                = $twig;
        $this->formActionsSettings = $formActionsSettings;
        $this->fieldModel          = $fieldModel;
        $this->leadModel = $leadModel;
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::FORM_ON_BUILD             => ['onFormBuilder', 0],
            FormActionsEvents::SAVE_CONTACT_FIELD => ['saveContactField', 0],
        ];
    }

    public function onFormBuilder(FormBuilderEvent $event)
    {
        $action = [
            'group'             => 'mautic.lead.lead.submitaction',
            'label'             => 'mautic.formactions.save_field',
            'description'       => 'mautic.formactions.save_field.desc',
            'formType'          => FormActionSaveField::class,
            'eventName'         => FormActionsEvents::SAVE_CONTACT_FIELD,
            'allowCampaignForm' => true,
        ];
        $event->addSubmitAction('lead.save_field', $action);
    }

    public function saveContactField(SubmissionEvent $event)
    {
        if (!$this->formActionsSettings->isEnabled()) {
            return;
        }

        $results = $event->getSubmission()->getResults();
        $lead    = $event->getSubmission()->getLead();
        $config = $event->getActionConfig();

        $fields = [
            'formfield'    => $results,
            'contactfield' => $lead->getProfileFields(),
        ];

        
        // Register filter only once
        $isFilterRegistered = false;
        if ($isFilterRegistered == true) {
        
            // TWIG filter json_decode
            $this->$twig->addFilter(new TwigFilter('unescape', function ($string) {
                return html_entity_decode($string);
            }));

            // Set the flag to true after registering
            $isFilterRegistered = true;
        }
        
        $this->leadModel->setFieldValues(
            $lead,
            [$config['field'] => $this->twig->createTemplate($config['syntax'])->render($fields)],
            $config['overwriteWithBlank']
        );

        $this->leadModel->saveEntity($lead);

    }
}
