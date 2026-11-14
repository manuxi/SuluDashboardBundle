<?php

declare(strict_types=1);

namespace Manuxi\SuluDashboardBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\View\View;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderInterface;

/**
 * The view of another admin class on a new path. Sulu's view builders keep their view private and a view has no setter for
 * its path, so this builder starts from a new view and takes over the options and the parent of the old one.
 */
final class RelocatedViewBuilder implements ViewBuilderInterface
{
    private View $view;

    public function __construct(View $old, View $new)
    {
        $new->mergeViewOptions($old);
        if (null !== $old->getParent()) {
            $new->setParent($old->getParent());
        }

        $this->view = $new;
    }

    public function getName(): string
    {
        return $this->view->getName();
    }

    public function setType(string $type): ViewBuilderInterface
    {
        $this->view->setType($type);

        return $this;
    }

    public function setOption(string $key, $value): ViewBuilderInterface
    {
        $this->view->setOption($key, $value);

        return $this;
    }

    public function setAttributeDefault(string $key, string $value): ViewBuilderInterface
    {
        $this->view->setAttributeDefault($key, $value);

        return $this;
    }

    public function setParent(string $parent): ViewBuilderInterface
    {
        $this->view->setParent($parent);

        return $this;
    }

    public function addRerenderAttribute(string $attribute): ViewBuilderInterface
    {
        $this->view->addRerenderAttribute($attribute);

        return $this;
    }

    public function getView(): View
    {
        return clone $this->view;
    }
}
