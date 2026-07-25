<?php

namespace App\Support\ActivityLog;

use App\Filament\Clusters\Provoz\Resources\Users\Schemas\StaffProfileSection;
use App\Mason\BrickRegistry;
use App\Mason\EmailBrickRegistry;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

/**
 * Czech field labels harvested from the schemas that define them, so the
 * activity log can name a nested key inside a Mason brick, a repeater or a
 * builder exactly as the admin sees it in the form ("Nadtitulek", not
 * "Eyebrow").
 *
 * Only explicitly set labels are taken — Filament otherwise derives one from the
 * field name, which would put English back into the log. Anything without a
 * declared label falls through to {@see ActivityPresenter::attributeLabel()}.
 */
class FieldLabels
{
    /**
     * App schemas outside the Mason registries whose repeaters back a JSON
     * column, and therefore surface as nested keys in the log.
     *
     * @var list<array{class-string, string}>
     */
    private const SCHEMA_PROVIDERS = [
        [StaffProfileSection::class, 'components'],
    ];

    /** @var array<string, array<string, string>>|null Brick id → field → label. */
    private static ?array $byBrick = null;

    /** @var array<string, array<string, string>> Model class → field → label. */
    private static array $byModel = [];

    /** @var array<string, string>|null Every declared label, brick scope flattened. */
    private static ?array $all = null;

    /**
     * Labels a model's own Filament resource form declares for its columns, so a
     * logged column is named exactly as the admin edits it ("Obsah stránky", not
     * the generic "Obsah").
     *
     * @param  string|null  $subjectType  A model class or its morph alias.
     * @return array<string, string>
     */
    public static function forModel(?string $subjectType): array
    {
        if ($subjectType === null) {
            return [];
        }

        $model = Relation::getMorphedModel($subjectType) ?? $subjectType;

        if (array_key_exists($model, self::$byModel)) {
            return self::$byModel[$model];
        }

        return self::$byModel[$model] = self::harvestModel($model);
    }

    /**
     * @return array<string, string>
     */
    private static function harvestModel(string $model): array
    {
        if (! class_exists($model)) {
            return [];
        }

        try {
            foreach (Filament::getResources() as $resource) {
                if ($resource::getModel() !== $model) {
                    continue;
                }

                return self::fromComponents($resource::form(Schema::make())->getComponents());
            }
        } catch (Throwable) {
            // No panel context, or a form that cannot be built without a record.
        }

        return [];
    }

    /**
     * Labels declared by one Mason brick's configuration form, including the
     * fields of any repeater nested inside it.
     *
     * @return array<string, string>
     */
    public static function forBrick(string $brickId): array
    {
        return self::byBrick()[$brickId] ?? [];
    }

    /**
     * Every label the app declares anywhere, as a last-resort dictionary for a
     * key whose owning brick is unknown.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $labels = [];

        foreach (self::byBrick() as $brickLabels) {
            $labels += $brickLabels;
        }

        foreach (self::SCHEMA_PROVIDERS as [$class, $method]) {
            try {
                $labels += self::fromComponents($class::$method());
            } catch (Throwable) {
                // A schema that cannot be built outside a form context simply
                // contributes nothing.
            }
        }

        return self::$all = $labels;
    }

    /**
     * Walks a component tree and collects every explicitly labelled field.
     *
     * @param  iterable<mixed>  $components
     * @return array<string, string>
     */
    public static function fromComponents(iterable $components): array
    {
        $labels = [];

        self::walk($components, $labels);

        return $labels;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function byBrick(): array
    {
        if (self::$byBrick !== null) {
            return self::$byBrick;
        }

        $byBrick = [];

        /** @var class-string<Brick> $brick */
        foreach ([...BrickRegistry::flat(), ...EmailBrickRegistry::flat()] as $brick) {
            try {
                $schema = $brick::configureBrickAction(Action::make('configure'))->getSchema(Schema::make());
                $byBrick[$brick::getId()] = $schema === null ? [] : self::fromComponents($schema->getComponents());
            } catch (Throwable) {
                $byBrick[$brick::getId()] = [];
            }
        }

        return self::$byBrick = $byBrick;
    }

    /**
     * @param  iterable<mixed>  $components
     * @param  array<string, string>  $labels
     */
    private static function walk(iterable $components, array &$labels): void
    {
        foreach ($components as $component) {
            if (! $component instanceof Component) {
                continue;
            }

            self::collect($component, $labels);
            self::walk(self::childrenOf($component), $labels);

            // Builder blocks each carry their own field set.
            if ($component instanceof Builder) {
                try {
                    foreach ($component->getBlocks() as $block) {
                        self::walk(self::childrenOf($block), $labels);
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $labels
     */
    private static function collect(Component $component, array &$labels): void
    {
        if (! method_exists($component, 'getName') || ! method_exists($component, 'hasCustomLabel')) {
            return;
        }

        try {
            if (! $component->hasCustomLabel()) {
                return;
            }

            $name = $component->getName();
            $label = $component->getLabel();
        } catch (Throwable) {
            return;
        }

        if ($label instanceof Htmlable) {
            $label = strip_tags($label->toHtml());
        }

        if (! is_string($name) || $name === '' || ! is_string($label) || trim($label) === '') {
            return;
        }

        // The outermost declaration wins, so a top-level field is not renamed by
        // a same-named field deeper in a repeater.
        $labels[$name] ??= trim($label);
    }

    /**
     * @return array<mixed>
     */
    private static function childrenOf(Component $component): array
    {
        try {
            $children = $component->getDefaultChildComponents();
        } catch (Throwable) {
            return [];
        }

        if ($children instanceof Schema) {
            return $children->getComponents();
        }

        return is_array($children) ? $children : [];
    }
}
