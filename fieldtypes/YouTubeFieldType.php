<?php

namespace Craft;

/**
 * YouTube FieldType.
 *
 * Upload video assets to YouTube.
 *
 * @author    Bob Olde Hampsink <b.oldehampsink@itmundi.nl>
 * @copyright Copyright (c) 2015, Itmundi
 * @license   http://buildwithcraft.com/license Craft License Agreement
 *
 * @link      http://github.com/boboldehampsink
 */
class YouTubeFieldType extends AssetsFieldType
{
    /**
     * Get fieldtype name.
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('YouTube Upload');
    }

    /**
     * We're going to save an array of YouTube Video settings.
     *
     * @return mixed
     */
    public function defineContentAttribute()
    {
        return AttributeType::Mixed;
    }

    /**
     * Override default asset settings - leaving fileKinds out.
     *
     * @return string|null
     */
    public function getSettingsHtml()
    {
        // Create a list of folder options for the main Source setting, and source options for the upload location
        // settings.
        $folderOptions = array();
        $sourceOptions = array();

        foreach ($this->getElementType()->getSources() as $key => $source) {
            if (!isset($source['heading'])) {
                $folderOptions[] = array('label' => $source['label'], 'value' => $key);
            }
        }

        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = array('label' => $source->name, 'value' => $source->id);
        }

        $namespace = craft()->templates->getNamespace();
        $isMatrix = (strncmp($namespace, 'types[Matrix][blockTypes][', 26) === 0);

        return craft()->templates->render('youtube/settings/_fieldtype', array(
            'folderOptions'     => $folderOptions,
            'sourceOptions'     => $sourceOptions,
            'targetLocaleField' => $this->getTargetLocaleFieldHtml(),
            'settings'          => $this->getSettings(),
            'type'              => $this->getName(),
            'isMatrix'          => $isMatrix,
        ));
    }

    /**
     * Return criteria in back-end and YT ID(s) in front-end.
     *
     * @param mixed $value
     *
     * @return ElementCriteriaModel|array
     */
    public function prepValue($value)
    {
        // Behave as normal asset in back-end
        if (craft()->request->isCpRequest()) {

            // Overwrite value, if any
            if ($value) {

                // Fetch target id
                $result = craft()->db->createCommand()
                                 ->select('targetId')
                                 ->from('relations')
                                 ->where(array(
                                     'fieldId'  => $this->model->id,
                                     'sourceId' => $this->element->id,
                                 ))
                                 ->queryRow();

                // If db result is valid
                if ($result && is_array($result)) {

                    // Keep values only
                    $value = array_values($result);
                } else {

                    // Else return nothing
                    $value = null;
                }
            }

            // Return with new values
            return parent::prepValue($value);
        }

        // Prepare for models
        $videos = array();
        foreach ($value as $id) {
            $videos[] = array('id' => $id);
        }

        // Return video models
        return YouTube_VideoModel::populateModels($videos);
    }

    /**
     * Send video off to YouTube after saving.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function onAfterElementSave()
    {
        // Let AssetsFieldType handle the default upload
        parent::onAfterElementSave();

        // Get asset id's
        $assets = array();
        $handle = $this->model->handle;
        $elementFiles = $this->element->{$handle};
        if ($elementFiles instanceof ElementCriteriaModel) {

            // Do we have any assets?
            if ($elementFiles->total()) {

                // UNCOMMENT THIS FOR DEBUGGING
                //Craft::dd(craft()->youTube->process($this->element, $elementFiles->first(), $this->model->handle, 0));

                // Now its our turn
                craft()->tasks->createTask('YouTube_Upload', Craft::t('Uploading video(s) to YouTube'), array(
                    'element'   => $this->element,
                    'model'     => $this->model,
                    'assets'    => $elementFiles->ids(),
                ));
            }
        }
    }

    // Protected
    // =========================================================================

    /**
     * Let users know we're uploading a video.
     *
     * @return string
     */
    protected function getAddButtonLabel()
    {
        return Craft::t('Add a video');
    }

    /**
     * Limit filekinds to video only.
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array_merge(parent::defineSettings(), array(
            'restrictFiles' => array(AttributeType::Bool, 'default' => true),
            'allowedKinds'  => array(AttributeType::Mixed, 'default' => array('video')),
        ));
    }
}