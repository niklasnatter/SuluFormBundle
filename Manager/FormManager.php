<?php

namespace L91\Sulu\Bundle\FormBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use L91\Sulu\Bundle\FormBundle\Entity\Form;
use L91\Sulu\Bundle\FormBundle\Repository\FormRepository;

/**
 * Generated by https://github.com/alexander-schranz/sulu-backend-bundle.
 */
class FormManager
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var FormRepository
     */
    protected $repository;

    /**
     * EventManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param FormRepository $formRepository
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        FormRepository $formRepository
    ) {
        $this->entityManager = $entityManager;
        $this->formRepository = $formRepository;
    }

    /**
     * @param int $id
     * @param string $locale
     *
     * @return Form
     */
    public function findById($id, $locale = null)
    {
        return $this->formRepository->findById($id, $locale);
    }

    /**
     * @param string $locale
     * @param array $filters
     *
     * @return Form[]
     */
    public function findAll($locale = null, $filters = [])
    {
        return $this->formRepository->findAll($locale, $filters);
    }

    /**
     * @param string $locale
     * @param array $filters
     *
     * @return int
     */
    public function count($locale = null, $filters = [])
    {
        return $this->formRepository->count($locale, $filters);
    }

    /**
     * @param array $data
     * @param string $locale
     * @param int $id
     *
     * @return Form
     */
    public function save($data, $locale = null, $id = null)
    {
        $form = new Form();

        // find exist or create new entity
        if ($id) {
            $form = $this->findById($id, $locale);
        }

        // Translation
        $translation = $form->getTranslation($locale, true);
        $translation->setTitle(self::getValue($data, 'title'));
        $translation->setSubject(self::getValue($data, 'subject'));
        $translation->setFromEmail(self::getValue($data, 'fromEmail'));
        $translation->setFromName(self::getValue($data, 'fromName'));
        $translation->setToEmail(self::getValue($data, 'toEmail'));
        $translation->setToName(self::getValue($data, 'toName'));
        $translation->setChanged(new \DateTime());

        // Add Translation to Form
        if (!$translation->getId()) {
            $translation->setForm($form);
            $form->addTranslation($translation);
        }

        // Set Default Locale
        if (!$form->getId()) {
            $form->setDefaultLocale($locale);
        }

        // Fields
        $reservedKeys = array_column(self::getValue($data, 'fields', []), 'key');
        $counter = 0;

        foreach (self::getValue($data, 'fields', []) as $fieldData) {
            ++$counter;
            $fieldType = self::getValue($fieldData, 'type');
            $fieldKey = self::getValue($fieldData, 'key');

            if (!$fieldKey) {
                $fieldKey = $this->getUniqueKey($fieldType, $reservedKeys);
            }

            $field = $form->getField($fieldKey, true);
            $field->setOrder($counter);
            $field->setType($fieldType);
            $field->setWidth(self::getValue($fieldData, 'width', 'full'));
            $field->setRequired(self::getValue($fieldData, 'required', false));

            // Field Translation
            $fieldTranslation = $field->getTranslation($locale, true);
            $fieldTranslation->setTitle(self::getValue($fieldData, 'title'));
            $fieldTranslation->setPlaceholder(self::getValue($fieldData, 'placeholder'));
            $fieldTranslation->setDefaultValue(self::getValue($fieldData, 'defaultValue'));


            // Field OPtions
            $prefix = 'options[';

            $keys = array_filter(array_keys($fieldData), function ($key) use ($prefix) {
                return strpos($key, $prefix) === 0;
            });

            $options = array_intersect_key($fieldData, array_flip($keys));

            foreach ($options as $key => $value) {
                unset($options[$key]);
                $options[trim(substr($key, strlen($prefix)), ']')] = $value;
            }

            $fieldTranslation->setOptions($options);

            // Add Translation to Field
            if (!$fieldTranslation->getId()) {
                $fieldTranslation->setField($field);
                $field->addTranslation($fieldTranslation);
            }

            // Add Field to Form
            if (!$field->getId()) {
                $field->setDefaultLocale($locale);
                $field->setForm($form);
                $form->addField($field);
            }
        }

        // Remove Fields
        foreach ($form->getFieldsNotInArray($reservedKeys) as $deletedField) {
            $form->removeField($deletedField);
            $this->entityManager->remove($deletedField);
        }

        // Save
        $this->entityManager->persist($form);
        $this->entityManager->flush();

        if (!$id) {
            // to avoid lazy load of sub entities in the serializer reload whole object with sub entities from db
            // remove this when you don`t join anything in `findById`
            $form = $this->findById($form->getId(), $locale);
        }

        return $form;
    }

    /**
     * @param int $id
     * @param string $locale
     *
     * @return Form|null
     */
    public function delete($id, $locale = null)
    {
        $object = $this->findById($id, $locale);

        if (!$object) {
            return;
        }

        $this->entityManager->remove($object);
        $this->entityManager->flush();

        return $object;
    }

    /**
     * @param array $data
     * @param string $value
     * @param mixed $default
     * @param string $type
     *
     * @return mixed
     */
    protected static function getValue($data, $value, $default = null, $type = null)
    {
        if (isset($data[$value])) {
            if ($type === 'date') {
                if (!$data[$value]) {
                    return $default;
                }

                return new \DateTime($data[$value]);
            }

            return $data[$value];
        }

        return $default;
    }

    /**
     * @param string $type
     * @param array $keys
     * @param int $counter
     *
     * @return string
     */
    protected function getUniqueKey($type, &$keys, $counter = 0)
    {
        $name = $type;

        if ($counter) {
            $name .= $counter;
        }

        if (!in_array($name, $keys)) {
            $keys[] = $name;

            return $name;
        }

        return $this->getUniqueKey($type, $keys, ++$counter);
    }
}
