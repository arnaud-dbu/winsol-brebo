<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Form;
use Statamic\Rules\AllowedFile;
use Tests\TestCase;

/**
 * Een bestand passeert twee controles: `mimes` uit het blueprint kijkt naar
 * de inhoud, Statamic's AllowedFile naar de extensie in de bestandsnaam. Een
 * type dat het blueprint toelaat maar AllowedFile niet kent, wordt geweigerd.
 */
class UploadExtensionsTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function uploadvelden(): array
    {
        return [
            'offerte.attachment' => ['offerte', 'attachment'],
            'herstelling.photo' => ['herstelling', 'photo'],
            'herstelling.invoice' => ['herstelling', 'invoice'],
        ];
    }

    #[DataProvider('uploadvelden')]
    public function test_every_extension_the_blueprint_allows_gets_past_statamic(string $formulier, string $veld): void
    {
        $regels = Form::find($formulier)->blueprint()->fields()->all()->get($veld)->get('validate');
        $mimes = collect($regels)->first(fn (string $regel): bool => str_starts_with($regel, 'mimes:'));

        foreach (explode(',', substr($mimes, strlen('mimes:'))) as $extensie) {
            $validator = Validator::make(['bestand' => UploadedFile::fake()->create("bestand.{$extensie}")], ['bestand' => [new AllowedFile]]);

            $this->assertTrue($validator->passes(), "{$formulier}.{$veld} laat .{$extensie} toe, maar Statamic weigert het.");
        }
    }
}
