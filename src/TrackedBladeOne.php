<?php

namespace Volcy\Translator\Flight;

use eftec\bladeone\BladeOne;
use Volcy\Translator\RenderedViewsRegistry;

/**
 * Drop-in replacement for BladeOne that records every view name rendered
 * during the request. Covers both call styles BladeOne supports:
 *
 *   $blade->run('pages.example', [...]);
 *   $blade->setView('pages.example')->run();
 *
 * Deliberately left without parameter type hints on the overrides below,
 * so this stays compatible regardless of the exact signature in whatever
 * eftec/bladeone version is installed.
 */
class TrackedBladeOne extends BladeOne
{
    protected ?RenderedViewsRegistry $registry = null;
    protected $currentView = null;

    public function setRegistry(RenderedViewsRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function setView($view)
    {
        $this->currentView = $view;

        return parent::setView($view);
    }

    public function run($view = null, $variables = [], $mergeData = null)
    {
        $resolvedView = $view ?? $this->currentView;

        if ($resolvedView !== null && $this->registry !== null) {
            $this->registry->add($resolvedView);
        }

        return parent::run($view, $variables, $mergeData);
    }
}
