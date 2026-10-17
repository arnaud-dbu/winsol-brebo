<?php

namespace App\Services\Leadbot;

use Illuminate\Support\Str;
use Statamic\Facades\Form;
use Statamic\Fields\Field;

/**
 * De formulieren van de site, vertaald naar stappen voor de assistent.
 *
 * De blueprint van het formulier is de enige bron. De assistent vraagt
 * dezelfde velden, met dezelfde keuzes en dezelfde verplichtingen, en post
 * daarna naar hetzelfde endpoint als het formulier op de pagina. Een veld
 * dat later aan een blueprint wordt toegevoegd, vraagt de assistent dus
 * vanzelf mee: wat niet in de volgorde uit config/leadbot.php staat, komt
 * achteraan.
 */
class FormSchema
{
    public function __construct(private Knowledge $knowledge) {}

    /**
     * @return array<string, array{handle: string, title: string, steps: array<int, array<int, array<string, mixed>>>}>
     */
    public function all(string $locale): array
    {
        return collect(config('leadbot.forms'))
            ->keys()
            ->mapWithKeys(fn (string $handle) => [$handle => $this->form($handle, $locale)])
            ->filter()
            ->all();
    }

    /**
     * Eén formulier als reeks stappen. Een stap is één veld, of een groep
     * velden die samen op één kaartje gevraagd worden (zoals de
     * contactgegevens of de akkoordverklaringen).
     *
     * @return array{handle: string, title: string, steps: array<int, array<int, array<string, mixed>>>}|null
     */
    public function form(string $handle, string $locale): ?array
    {
        $form = Form::find($handle);

        if (! $form) {
            return null;
        }

        $fields = $form->blueprint()->fields()->all()
            ->reject(fn (Field $field) => $field->handle() === 'language')
            ->map(fn (Field $field) => $this->describe($handle, $field, $locale))
            ->filter();

        $order = config("leadbot.forms.{$handle}.order", []);
        $placed = collect($order)->flatten()->all();
        $steps = [];

        foreach ($order as $step) {
            $group = collect((array) $step)->map(fn ($name) => $fields->get($name))->filter()->values()->all();

            if ($group !== []) {
                $steps[] = $group;
            }
        }

        foreach ($fields->except($placed) as $field) {
            $steps[] = [$field];
        }

        return [
            'handle' => $handle,
            'title' => __((string) $form->title(), [], $locale),
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function describe(string $form, Field $field, string $locale): ?array
    {
        $config = $field->config();
        $type = $field->type();
        $rules = collect($config['validate'] ?? [])->map(fn ($rule) => (string) $rule);
        $handle = $field->handle();

        $kind = match (true) {
            $type === 'product_reference' => 'product',
            $type === 'assets' => 'files',
            in_array($type, ['range_checkboxes', 'brochure_checkboxes', 'checkboxes'], true) => 'multi',
            in_array($type, ['select', 'location_select', 'range_select', 'radio'], true) => 'choice',
            $type === 'toggle' && $rules->contains('accepted') => 'consent',
            $type === 'toggle' => 'yesno',
            $type === 'textarea' => 'textarea',
            ($config['input_type'] ?? null) === 'email' || $handle === 'email' => 'email',
            ($config['input_type'] ?? null) === 'tel' || $handle === 'phone' => 'phone',
            $handle === 'address' => 'address',
            default => 'text',
        };

        $label = __((string) ($config['display'] ?? Str::headline($handle)), [], $locale);
        $instructions = isset($config['instructions']) ? __((string) $config['instructions'], [], $locale) : null;
        $askKey = "leadbot.ask.{$form}.{$handle}";
        $genericKey = "leadbot.ask.{$handle}";

        return array_filter([
            'handle' => $handle,
            'kind' => $kind,
            'label' => $label,
            'ask' => $this->translated($askKey, $locale) ?? $this->translated($genericKey, $locale) ?? $label,
            'instructions' => $instructions,
            'placeholder' => isset($config['placeholder']) ? __((string) $config['placeholder'], [], $locale) : null,
            'required' => $rules->contains('required') || $rules->contains('accepted') || ($config['required'] ?? false),
            'options' => $this->options($type, $config, $locale),
            'max_files' => $kind === 'files' ? (int) ($config['max_files'] ?? 1) : null,
            'accept' => $kind === 'files' ? $this->accept($rules->all()) : null,
            'integer' => $rules->contains('integer') ?: null,
            'min' => $this->bound($rules->all(), 'min'),
            'max' => $this->bound($rules->all(), 'max'),
            'year' => $rules->contains(fn ($rule) => str_contains($rule, 'InstallationYear')) ?: null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{value: string, label: string}>|null
     */
    private function options(string $type, array $config, string $locale): ?array
    {
        $options = match ($type) {
            'range_checkboxes', 'range_select' => collect($this->knowledge->ranges($locale))
                ->map(fn ($range) => ['value' => $range['handle'], 'label' => $range['title']]),
            'location_select' => collect($this->knowledge->showrooms())
                ->map(fn ($showroom) => ['value' => $showroom['slug'], 'label' => $showroom['name']]),
            'brochure_checkboxes' => collect($this->knowledge->brochures($locale))
                ->map(fn ($brochure) => ['value' => $brochure['file'], 'label' => $brochure['label']]),
            'select', 'checkboxes', 'radio' => collect($config['options'] ?? [])
                ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => __((string) $label, [], $locale)])
                ->values(),
            default => null,
        };

        return $options?->values()->all();
    }

    /**
     * @param  array<int, string>  $rules
     */
    private function accept(array $rules): string
    {
        $mimes = collect($rules)->first(fn ($rule) => str_starts_with($rule, 'mimes:'));

        return $mimes
            ? collect(explode(',', Str::after($mimes, 'mimes:')))->map(fn ($extension) => '.'.$extension)->implode(',')
            : 'image/*';
    }

    /**
     * @param  array<int, string>  $rules
     */
    private function bound(array $rules, string $which): ?int
    {
        $between = collect($rules)->first(fn ($rule) => str_starts_with($rule, 'between:'));

        if ($between) {
            [$min, $max] = explode(',', Str::after($between, 'between:'));

            return (int) ($which === 'min' ? $min : $max);
        }

        return null;
    }

    private function translated(string $key, string $locale): ?string
    {
        $value = __($key, [], $locale);

        return $value === $key ? null : $value;
    }
}
