<?php

namespace Arno14\DoctrineChangeDetector;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;

class ChangeDetectorListener implements \Doctrine\Common\EventSubscriber
{
    /**
     * @var array<string, array<string,array<string,array{php:mixed,db:mixed}>>
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
        print_r(__METHOD__."\n");
        $entity = $args->getObject();
        /** @var EntityManager $em */
        $em = $args->getObjectManager();
        $meta = $em->getClassMetadata(get_class($entity));


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

            // print_r(__METHOD__." for field $name : useDbValue=".($useDbValue?'true':'false')."\n");
            // $value = $meta->getFieldValue($entity, $field);
            // $meta->setFieldValue($entity, $field, $value);
        }

        print_r(__METHOD__." stored original values: ".json_encode($this->originalValues)."\n");
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        print_r(__METHOD__."\n".json_encode($this->originalValues));

        /** @var EntityManager $em */
        $em = $args->getObjectManager();

        foreach ($em->getUnitOfWork()->getIdentityMap() as $class => $entities) {

            foreach ($entities as $entity) {

                print_r($entity);

                $oid = spl_object_id($entity);

                if (!isset($this->originalValues[$oid])) {
                    continue;
                }

                foreach ($this->originalValues[$oid] as $fieldName => $originalValues) {
                    print_r($originalValues);

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

                        //DB values are different but PHP values are the same
                        // force update by recreating an instance of the PHP value
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

    public function postFlush(): void
    {
        //After flush, retrieve the newly affected original values
        // $this->originalValues = [];
    }

    public function onClear(): void
    {
        $this->originalValues = [];
    }
}
