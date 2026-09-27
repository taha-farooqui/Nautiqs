<?php

namespace Tests\Unit;

use App\Support\Pii\Mask;
use App\Support\Pii\PiiCipher;
use App\Support\Pii\Search;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The parts of client-data encryption that need no database: the cipher's
 * transition rules, the superadmin masks, and in-memory search. The model and
 * migration paths are exercised against MongoDB separately.
 */
class PiiTest extends TestCase
{
    private Encrypter $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useKey(Encrypter::generateKey('AES-256-CBC'));
    }

    private function useKey(string $raw): void
    {
        $this->key = new Encrypter($raw, 'AES-256-CBC');
        Facade::clearResolvedInstances();
        Crypt::swap($this->key);
    }

    /* ------------------------------------------------------------- cipher */

    public function test_round_trip(): void
    {
        $c = PiiCipher::encrypt('jean.dupont@gmail.com');

        $this->assertNotSame('jean.dupont@gmail.com', $c);
        $this->assertStringNotContainsString('dupont', $c);
        $this->assertSame('jean.dupont@gmail.com', PiiCipher::decrypt($c));
    }

    public function test_same_value_encrypts_differently_each_time(): void
    {
        // A random IV per value: two clients called Dupont are not linkable
        // by comparing ciphertext.
        $this->assertNotSame(PiiCipher::encrypt('Dupont'), PiiCipher::encrypt('Dupont'));
    }

    public function test_empty_and_null_stay_as_they_are(): void
    {
        $this->assertNull(PiiCipher::encrypt(null));
        $this->assertSame('', PiiCipher::encrypt(''));
        $this->assertNull(PiiCipher::decrypt(null));
        $this->assertSame('', PiiCipher::decrypt(''));
    }

    public function test_legacy_plaintext_reads_as_is(): void
    {
        // The code ships before the migration runs; old rows must still read.
        $this->assertSame('06 28 92 21 93', PiiCipher::decrypt('06 28 92 21 93'));
        $this->assertSame('Jean', PiiCipher::decrypt('Jean'));
    }

    public function test_already_encrypted_is_not_encrypted_twice(): void
    {
        $once = PiiCipher::encrypt('Dupont');
        $this->assertSame($once, PiiCipher::encrypt($once));
    }

    public function test_numbers_are_stored_as_strings(): void
    {
        // A postcode like 06000 must not come back as 6000.
        $this->assertSame('06000', PiiCipher::decrypt(PiiCipher::encrypt('06000')));
        $this->assertSame('6000', PiiCipher::decrypt(PiiCipher::encrypt(6000)));
    }

    public function test_wrong_key_fails_loudly_instead_of_returning_ciphertext(): void
    {
        $c = PiiCipher::encrypt('Dupont');
        $this->useKey(Encrypter::generateKey('AES-256-CBC'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY');
        PiiCipher::decrypt($c);
    }

    public function test_wrong_key_ciphertext_is_never_wrapped_again(): void
    {
        $c = PiiCipher::encrypt('Dupont');
        $this->useKey(Encrypter::generateKey('AES-256-CBC'));

        $this->expectException(RuntimeException::class);
        PiiCipher::encrypt($c);
    }

    public function test_tampered_ciphertext_is_rejected(): void
    {
        $payload = json_decode(base64_decode(PiiCipher::encrypt('Dupont')), true);
        $payload['value'] = base64_encode(str_repeat('x', 16));
        $tampered = base64_encode(json_encode($payload));

        $this->assertFalse(PiiCipher::isEncrypted($tampered));
        $this->expectException(RuntimeException::class);
        PiiCipher::decrypt($tampered);
    }

    public function test_unchanged_value_keeps_its_ciphertext(): void
    {
        $stored = PiiCipher::encrypt('Dupont');

        $this->assertSame($stored, PiiCipher::encryptKeeping('Dupont', $stored));
        $this->assertNotSame($stored, PiiCipher::encryptKeeping('Durand', $stored));
        $this->assertSame('Durand', PiiCipher::decrypt(PiiCipher::encryptKeeping('Durand', $stored)));
    }

    public function test_only_named_keys_of_an_array_are_encrypted(): void
    {
        $snap = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'city' => 'Nice', 'email' => null];
        $enc  = PiiCipher::encryptKeys($snap, ['first_name', 'last_name', 'email']);

        $this->assertSame('Nice', $enc['city']);
        $this->assertNull($enc['email']);
        $this->assertTrue(PiiCipher::isEncrypted($enc['last_name']));
        $this->assertSame($snap, PiiCipher::decryptKeys($enc, ['first_name', 'last_name', 'email']));
    }

    public function test_looks_encrypted_ignores_ordinary_text(): void
    {
        foreach (['Dupont', 'eyJ', 'jean@x.com', str_repeat('a', 200), 'eyJ' . str_repeat('A', 200)] as $s) {
            $this->assertFalse(PiiCipher::looksEncrypted($s), $s);
        }
        $this->assertTrue(PiiCipher::looksEncrypted(PiiCipher::encrypt('x')));
    }

    /* --------------------------------------------------------------- masks */

    public function test_masks_show_shape_not_value(): void
    {
        $this->assertSame('J••• D•••', Mask::name('Jean Dupont'));
        $this->assertSame('H•••', Mask::name('hélène'));
        $this->assertSame('', Mask::name(''));

        $this->assertSame('j•••@g•••.com', Mask::email('jean.dupont@gmail.com'));
        $this->assertSame('t•••@a•••.fr', Mask::email('taha@alphaventure.fr'));
        $this->assertSame('', Mask::email(null));
        $this->assertSame('•••', Mask::email('not an email'));

        $this->assertSame('•• •• •• •• 93', Mask::phone('06 28 92 21 93'));
        $this->assertSame('•• •• •• •• 93', Mask::phone('+33628922193'));
        $this->assertSame('', Mask::phone(''));

        $this->assertSame('•••', Mask::text('cash buyer, divorcing'));
    }

    public function test_masks_leak_nothing_identifying(): void
    {
        foreach ([Mask::name('Jean Dupont'), Mask::email('jean.dupont@dupont-yachts.fr')] as $m) {
            $this->assertStringNotContainsStringIgnoringCase('dupont', $m);
            $this->assertStringNotContainsStringIgnoringCase('jean', $m);
        }
        $this->assertStringNotContainsString('28 92 21', Mask::phone('06 28 92 21 93'));
    }

    /* -------------------------------------------------------------- search */

    private function clients(): \Illuminate\Support\Collection
    {
        return collect([
            (object) ['first' => 'Hélène', 'last' => 'Durand', 'email' => 'helene@x.fr', 'phone' => '06 11 22 33 44', 'city' => 'Nice'],
            (object) ['first' => 'Jean',   'last' => 'Dupont', 'email' => 'jean@y.com',  'phone' => '06 28 92 21 93', 'city' => 'Antibes'],
            (object) ['first' => 'Marc',   'last' => 'Élie',   'email' => 'm@z.fr',      'phone' => '',               'city' => 'Cannes'],
        ]);
    }

    private function find(string $q): array
    {
        return Search::filter($this->clients(), $q, fn ($c) => [$c->first, $c->last, $c->email, $c->phone, $c->city])
            ->pluck('last')->all();
    }

    public function test_search_matches_what_the_database_did(): void
    {
        $this->assertSame(['Dupont'], $this->find('dup'));        // fragment of surname
        $this->assertSame(['Dupont'], $this->find('DUPONT'));     // case-blind
        $this->assertSame(['Dupont'], $this->find('jean@y'));     // part of an email
        $this->assertSame(['Dupont'], $this->find('06 28 92'));   // phone as stored
        $this->assertSame(['Durand'], $this->find('nice'));       // city
        $this->assertSame(['Durand', 'Dupont', 'Élie'], $this->find(''));  // no query = everything
    }

    public function test_search_improves_on_the_database(): void
    {
        $this->assertSame(['Durand'], $this->find('helene'));       // accent-blind
        $this->assertSame(['Élie'], $this->find('elie'));
        $this->assertSame(['Dupont'], $this->find('0628'));          // phone without spaces
        $this->assertSame(['Dupont'], $this->find('+33 6 28 92'));   // international form
    }

    public function test_short_digit_queries_do_not_match_every_phone(): void
    {
        $this->assertSame([], $this->find('28 9x'));
        $this->assertSame([], $this->find('99'));
    }

    public function test_surnames_file_accent_blind(): void
    {
        $sorted = $this->clients()->sortBy(fn ($c) => Search::nameKey($c->last, $c->first))->pluck('last')->all();
        $this->assertSame(['Dupont', 'Durand', 'Élie'], array_values($sorted));
    }

    public function test_pagination_keeps_the_query_string(): void
    {
        $req = Request::create('/clients', 'GET', ['q' => 'x', 'page' => 2]);
        $p = Search::paginate(collect(range(1, 45)), $req, 20);

        $this->assertSame(45, $p->total());
        $this->assertSame([21, 22, 23], array_slice($p->items(), 0, 3));
        $this->assertStringContainsString('q=x', $p->url(3));
    }
}
