<?php

namespace FourChimps\CrudGeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator as BaseDoctrineFormGenerator;

class DoctrineFormGenerator extends BaseDoctrineFormGenerator
{
    // base class has private members used in public methods so we need to manage our own
    private $_filesystem;
    private $_skeletonDir;
    private $_className;
    private $_classPath;

    protected $entityBundle;

    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->_filesystem = $filesystem;
        $this->_skeletonDir = $skeletonDir;
    }

    public function getClassName()
    {
        return $this->_className;
    }

    public function getClassPath()
    {
        return $this->_classPath;
    }

    public function setEntityBundle($entityBundle)
    {
        $this->entityBundle = $entityBundle;
        return $this;
    }

    /**
     * Generates the entity form class if it does not exist.
     *
     * @param BundleInterface   $bundle   The bundle in which to create the class
     * @param string            $entity   The entity relative class name
     * @param ClassMetadataInfo $metadata The entity metadata class
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata)
    {
        $parts       = explode('\\', $entity);
        $entityClass = array_pop($parts);

        $this->_className = $entityClass.'Type';
        $dirPath         = $bundle->getPath().'/Form';
        $this->_classPath = $dirPath.'/'.str_replace('\\', '/', $entity).'Type.php';

        if (file_exists($this->getClassPath())) {
            throw new \RuntimeException(sprintf('Unable to generate the %s form class as it already exists under the %s file', $this->_className, $this->_classPath));
        }

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The form generator does not support entity classes with multiple primary keys.');
        }

        $parts = explode('\\', $entity);
        array_pop($parts);

        $this->renderFile($this->_skeletonDir, 'FormType.php', $this->getClassPath(), array(
            'dir'              => $this->_skeletonDir,
            'fields'           => $this->getFieldsFromMetadata($metadata),
            'namespace'        => $bundle->getNamespace(),
            'entity_bundle'    => $this->entityBundle->getNamespace(),
            'entity_namespace' => implode('\\', $parts),
            'entity_class'     => $entityClass,
            'form_class'       => $this->getClassName(),
            'form_type_name'   => strtolower(str_replace('\\', '_', $bundle->getNamespace()).($parts ? '_' : '').implode('_', $parts).'_'.$this->_className),
        ));
    }

    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param ClassMetadataInfo $metadata
     * @return array $fields
     */
    private function getFieldsFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }
}
