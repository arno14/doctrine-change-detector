<?php

namespace Arno14\DoctrineChangeDetector;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Generator;

class ChangeDetectorListener implements \Doctrine\Common\EventSubscriber
{
    public const OPTION_NAME = 'detectChangeByDatabaseValue';

    /**
     * @var array<int,array<string,array{php:mixed,db:mixed}>>
     */
    private array $originalValues = [];

    /**
     * For optimization purposes, store the names of fields having the option detectChangeByDatabaseValue set to true
     * @var array<string,string[]>
     */
    private array $classNameConcerned = [];

    /**
     * For optimization purposes,store the names of classes not having any field with the option detectChangeByDatabaseValue set to true
     * @var array<string,true>
     */
    private array $classNameNotConcerned = [];

    public function getSubscribedEvents()
    {
        return [
            Events::postLoad,
            Events::preFlush,
            Events::postFlush,
            Events::onClear
        ];
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($this->isClassNotConcerned(get_class($entity))) {
            return;
        }

        /** @var EntityManager $em */
        $em = $args->getObjectManager();

        $this->registerEntityOriginalValues($entity, $em);
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        /** @var EntityManager $em */
        $em = $args->getObjectManager();

        foreach ($em->getUnitOfWork()->getIdentityMap() as $className => $entities) {

            if ($this->isClassNotConcerned($className)) {
                continue;
            }

            /** @var null|ClassMetadata<object> $classMetaData */
            $classMetaData = null;

            foreach ($entities as $entity) {

                $oid = spl_object_id($entity);

                if (!isset($this->originalValues[$oid])) {
                    continue;
                }

                foreach ($this->originalValues[$oid] as $fieldName => $originalValues) {

                    if (null === $classMetaData) {
                        $classMetaData = $em->getClassMetadata($className);
                    }

                    $type = Type::getType($classMetaData->fieldMappings[$fieldName]->type);

                    $originalDBValue = $originalValues['db'];
                    $originalPHPValue = $originalValues['php'];

                    $currentPHPValue = $classMetaData->getFieldValue($entity, $fieldName);
                    $currentDBValue = $type->convertToDatabaseValue(
                        $currentPHPValue,
                        $em->getConnection()->getDatabasePlatform()
                    );

                    if ($currentDBValue === $originalDBValue) {
                        // No change detected, revert to original php value
                        $classMetaData->setFieldValue($entity, $fieldName, $originalPHPValue);
                        continue;
                    }

                    // DB values are different
                    if ($originalPHPValue !== $currentPHPValue) {
                        // PHP values are also different, so UnitOfWork will detect the change
                        continue;
                    }

                    // PHP values are the same so UnitOfWork would not detect any change
                    // The update will be forced by recreating a new instance of the PHP value and setting it to the entity
                    $recreatedPHPValue = $type->convertToPHPValue(
                        $currentDBValue,
                        $em->getConnection()->getDatabasePlatform()
                    );
                    $classMetaData->setFieldValue($entity, $fieldName, $recreatedPHPValue);

                }
                unset($this->originalValues[$oid]);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->originalValues = [];

        /** @var EntityManager $em */
        $em = $args->getObjectManager();

        foreach ($em->getUnitOfWork()->getIdentityMap() as $className => $entities) {

            if ($this->isClassNotConcerned($className)) {
                continue;
            }

            $meta = $em->getClassMetadata($className);

            foreach ($entities as $entity) {
                $this->registerEntityOriginalValues($entity, $em, $meta);
            }
        }
    }

    public function onClear(): void
    {
        $this->originalValues = [];
        $this->classNameConcerned = [];
        $this->classNameNotConcerned = [];
    }


    /**
     * @param object $entity
     * @param ClassMetadata<object>|null $classMetadata
     */
    private function registerEntityOriginalValues(
        object $entity,
        EntityManager $em,
        ?ClassMetadata $classMetadata = null
    ): void {

        $classMetadata = (null === $classMetadata) ? $em->getClassMetadata(get_class($entity)) : $classMetadata;

        foreach ($this->iterateConcernedMapping($classMetadata) as $name => $field) {

            $oid = spl_object_id($entity);

            $type = Type::getType($field->type);

            $originalPHPValue = $classMetadata->getFieldValue($entity, $name);

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

    private function isClassNotConcerned(string $className): bool
    {
        return isset($this->classNameNotConcerned[$className]);
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     * @return Generator<string,FieldMapping>
     */
    private function iterateConcernedMapping(ClassMetadata $classMetadata): Generator
    {
        if ($this->isClassNotConcerned($classMetadata->name)) {
            return;
        }

        if (isset($this->classNameConcerned[$classMetadata->name])) {

            foreach ($this->classNameConcerned[$classMetadata->name] as $fieldName) {

                yield $fieldName => $classMetadata->fieldMappings[$fieldName];
            }
            return;
        }

        $concernedFields = [];

        foreach ($classMetadata->fieldMappings as $name => $fieldMapping) {

            $useDbValue = $fieldMapping->options[self::OPTION_NAME] ?? false;

            if (!$useDbValue) {

                continue;
            }

            $concernedFields[] = $name;

            yield $name => $fieldMapping;
        }

        if (count($concernedFields) > 0) {

            $this->classNameConcerned[$classMetadata->name] = $concernedFields;

        } else {

            $this->classNameNotConcerned[$classMetadata->name] = true;
        }
    }
}
