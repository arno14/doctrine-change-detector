<?php

namespace Arno14\DoctrineChangeDetector;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;

class ChangeDetectorListener implements \Doctrine\Common\EventSubscriber
{
    /**
     * @var array<int,array<string,array{php:mixed,db:mixed}>>
     */
    private array $originalValues = [];

    public function getSubscribedEvents()
    {
        return [
            Events::postLoad,
            Events::preFlush,
            Events::onClear
        ];
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        /** @var EntityManager $em */
        $em = $args->getObjectManager();
        $meta = $em->getClassMetadata(get_class($entity));


        //@todo avoid iterating on each entity, as metadata are the sames for a given class
        foreach ($meta->fieldMappings as $name => $field) {

            $useDbValue = $field->options['detectChangeByDatabaseValue'] ?? false;

            if (!$useDbValue) {
                continue;
            }

            $oid = spl_object_id($entity);

            $type = Type::getType($field->type);

            $originalPHPValue = $meta->getFieldValue($entity, $name);

            $originalDBValue = $type->convertToDatabaseValue(
                $originalPHPValue,
                $em->getConnection()->getDatabasePlatform()
            );

            $this->originalValues[$oid][$name] = [
                'php' => $originalPHPValue,
                'db'  => $originalDBValue,
            ];
        }
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        /** @var EntityManager $em */
        $em = $args->getObjectManager();

        foreach ($em->getUnitOfWork()->getIdentityMap() as $class => $entities) {

            foreach ($entities as $entity) {

                $oid = spl_object_id($entity);

                if (!isset($this->originalValues[$oid])) {
                    continue;
                }

                foreach ($this->originalValues[$oid] as $fieldName => $originalValues) {

                    $meta = $em->getClassMetadata(get_class($entity));
                    $type = Type::getType($meta->fieldMappings[$fieldName]->type);

                    $currentPHPValue = $meta->getFieldValue($entity, $fieldName);
                    $currentDBValue = $type->convertToDatabaseValue(
                        $currentPHPValue,
                        $em->getConnection()->getDatabasePlatform()
                    );
                    $originalDBValue = $originalValues['db'];
                    $originalPHPValue = $originalValues['php'];

                    if ($currentDBValue === $originalDBValue) {
                        // No change detected, revert to original php value
                        $meta->setFieldValue($entity, $fieldName, $originalPHPValue);
                        continue;
                    }

                    if ($originalPHPValue === $currentPHPValue) {

                        // DB values are different but PHP values are the same
                        // force update by recreating a new instance of the PHP value
                        $recreatedPHPValue = $type->convertToPHPValue(
                            $currentDBValue,
                            $em->getConnection()->getDatabasePlatform()
                        );
                        $meta->setFieldValue($entity, $fieldName, $recreatedPHPValue);
                        continue;
                    }

                }
                unset($this->originalValues[$oid]);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->originalValues = [];
        // @todo After flush, retrieve the newly affected original values
    }

    public function onClear(): void
    {
        $this->originalValues = [];
    }
}
