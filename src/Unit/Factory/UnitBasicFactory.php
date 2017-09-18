<?php

namespace Shopware\Unit\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Unit\Extension\UnitExtension;
use Shopware\Unit\Struct\UnitBasicStruct;

class UnitBasicFactory extends Factory
{
    const ROOT_NAME = 'unit';

    const FIELDS = [
       'id' => 'id',
       'uuid' => 'uuid',
       'short_code' => 'translation.short_code',
       'name' => 'translation.name',
    ];

    /**
     * @var UnitExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        UnitBasicStruct $unit,
        QuerySelection $selection,
        TranslationContext $context
    ): UnitBasicStruct {
        $unit->setId((int) $data[$selection->getField('id')]);
        $unit->setUuid((string) $data[$selection->getField('uuid')]);
        $unit->setShortCode((string) $data[$selection->getField('short_code')]);
        $unit->setName((string) $data[$selection->getField('name')]);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($unit, $data, $selection, $context);
        }

        return $unit;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'unit_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.unit_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }
}
