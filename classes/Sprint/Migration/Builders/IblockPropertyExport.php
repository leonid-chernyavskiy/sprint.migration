<?php

namespace Sprint\Migration\Builders;

use Sprint\Migration\Helpers\IblockHelper;
use Sprint\Migration\Module;
use Sprint\Migration\AbstractBuilder;
use Sprint\Migration\HelperManager;
use Sprint\Migration\Exceptions\HelperException;
use Sprint\Migration\Exceptions\RebuildException;

class IblockPropertyExport extends AbstractBuilder
{

    public function initialize() {
        $this->setTitle(GetMessage('SPRINT_MIGRATION_BUILDER_IblockPropertyExport'));
        $this->setTemplateFile(Module::getModuleDir() . '/templates/IblockPropertyExport.php');

        $this->requiredField('iblock_id', array(
            'title' => GetMessage('SPRINT_MIGRATION_BUILDER_IblockPropertyExport_IblockId'),
            'placeholder' => '',
            'width' => 250,
            'items' => $this->getIblocksStructure()
        ));

        $this->requiredField('property_ids', array(
            'title' => GetMessage('SPRINT_MIGRATION_BUILDER_IblockPropertyExport_PropertyIds'),
            'width' => 250,
            'multiple' => 1,
            'value' => array(),
            'items' => $this->getPropertiesStructure(
                $this->getFieldValue('iblock_id')
            )
        ));

    }


    public function execute() {

        $iblockId = $this->getFieldValue('iblock_id');
        $propertyIds = $this->getFieldValue('property_ids');

        if (is_numeric($propertyIds)) {
            $propertyIds = array($propertyIds);
        } elseif (!is_array($propertyIds)) {
            $propertyIds = array();
        }

        if (empty($iblockId) || empty($propertyIds)) {
            $this->exitWithMessage('empty iblock or property');
        }


        $iblockHelper = new IblockHelper();

        $iblock = $iblockHelper->getIblock(array('ID' => $iblockId));

        $this->exitIfEmpty($iblock, 'iblock not found');

        $iblockProperties = array();
        foreach ($propertyIds as $propertyId) {
            $iblockProperty = $iblockHelper->getProperty($iblockId, array('ID' => $propertyId));
            if ($iblockProperty) {
                unset($iblockProperty['ID']);
                unset($iblockProperty['IBLOCK_ID']);
                unset($iblockProperty['TIMESTAMP_X']);
                $iblockProperties[] = $iblockProperty;
            }
        }

        $this->exitIfEmpty($iblockProperties, 'properties not found');

        $this->setTemplateVar('iblock', $iblock);
        $this->setTemplateVar('iblockProperties', $iblockProperties);

    }

    public function getIblocksStructure() {

        $structure = [];
        $iblockHelper = new IblockHelper();

        $iblockTypes = $iblockHelper->getIblockTypes();

        foreach ($iblockTypes as $iblockType) {
            $structure[$iblockType['ID']] = [
                'title' => $iblockType['LANG'][LANGUAGE_ID]['NAME'],
                'items' => array(),
            ];
        }

        $iblocks = $iblockHelper->getIblocks();
        foreach ($iblocks as $iblock) {
            $structure[$iblock['IBLOCK_TYPE_ID']]['items'][] = [
                'title' => $iblock['NAME'],
                'value' => $iblock['ID']
            ];
        }

        return $structure;
    }

    public function getPropertiesStructure($iblockId) {
        $structure = [
            0 => ['title' => 'iblock', 'items' => []]
        ];
        $iblockHelper = new IblockHelper();

        $props = $iblockHelper->getProperties($iblockId);

        foreach ($props as $prop) {
            $structure[0]['items'][] = [
                'title' => $prop['NAME'],
                'value' => $prop['ID']
            ];
        }

        return $structure;
    }
}