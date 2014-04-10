<?php

namespace Sirian\SuggestBundle\Suggest;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

abstract class DoctrineSuggester implements SuggesterInterface
{
    /**
     * @var Options
     */
    protected $options;
    protected $registry;
    protected $propertyAccessor;

    public function __construct(ManagerRegistry $registry, $options)
    {
        $this->registry = $registry;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->options = $this->prepareOptions($options);
    }

    public function reverseTransform(array $ids)
    {
        return $this->getLoader()->getEntitiesByIds($this->options['id_property'], $ids);
    }

    public function transform($objects)
    {
        $result = [];
        foreach ($objects as $object) {
            $result[] = $this->transformObject($object);
        }
        return $result;
    }

    protected function transformObject($object)
    {
        $item = new Item();
        $item->id = $this->propertyAccessor->getValue($object, $this->options['id_property']);
        $item->text = $this->propertyAccessor->getValue($object, $this->options['property']);

        return $item;
    }

    public function suggest(SuggestQuery $query)
    {
        $entities = $this->getSuggestLoader($query)->getEntities();
        $hasMore = count($entities) > $this->options['limit'];
        $entities = array_slice($entities, 0, $this->getLimit());

        return new Result($this->transform($entities), $hasMore);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'manager' => null,
                'limit' => 20,
            ])
        ;

        $resolver->setRequired(['id_property', 'property', 'class', 'search']);

        $registry = $this->registry;
        $resolver->setNormalizers([
            'manager' => function (Options $options, $manager) use ($registry) {
                /* @var ManagerRegistry $registry */
                if (null !== $manager) {
                    return $registry->getManager($manager);
                }

                $manager = $registry->getManagerForClass($options['class']);

                if (null === $manager) {
                    throw new \RuntimeException(sprintf(
                        'Class "%s" seems not to be a managed Doctrine entity.' .
                        'Did you forget to map it?',
                        $options['class']
                    ));
                }

                return $manager;
            }
        ]);
    }

    protected function prepareOptions($options)
    {
        if (empty($options['search']) && !empty($options['property'])) {
            $options['search'] = [
                $options['property'] => 'middle'
            ];
        }
        $resolver = new OptionsResolver();
        $this->setDefaultOptions($resolver);
        return $resolver->resolve($options);
    }

    /**
     * @return ObjectManager
     */
    protected function getManager()
    {
        return $this->options['manager'];
    }

    protected function getLimit()
    {
        return $this->options['limit'];
    }


    /**
     * @return EntityLoaderInterface
     */
    abstract protected function getLoader();

    /**
     * @param SuggestQuery $query
     * @return EntityLoaderInterface
     */
    abstract protected function getSuggestLoader(SuggestQuery $query);
}