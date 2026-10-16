/**
 * Grapheme counting of the post text.
 */
import { afterEach, describe, expect, it } from 'vitest';
import { countCodePoints, countGraphemes } from '../../src/editor/graphemes';

const CASES = [
	[ 'ascii', 'Nueva entrada', 13 ],
	[ 'accents', 'Café ñandú', 10 ],
	[ 'combining accent', 'e\u0301', 1 ],
	[ 'consecutive emoji', '😀😀😀', 3 ],
	[ 'zwj emoji', '👩‍💻', 1 ],
	[ 'consecutive zwj emoji', '👩‍💻👩‍💻', 2 ],
	[ 'family', '👨‍👩‍👧‍👦', 1 ],
	[ 'skin tone', '👍🏽👍🏽', 2 ],
	[ 'flags', '🇪🇸🇬🇧', 2 ],
	[ 'keycap', '1️⃣', 1 ],
	[ 'mixed', 'Hola 👋🏽 #wordpress 🇬🇱', 19 ],
];

const Segmenter = Intl.Segmenter;

afterEach( () => {
	Intl.Segmenter = Segmenter;
} );

describe( 'countGraphemes', () => {
	it.each( CASES )( '%s', ( name, text, expected ) => {
		expect( countGraphemes( text ) ).toBe( expected );
	} );

	it( 'counts 300 and 301 graphemes', () => {
		expect( countGraphemes( 'a'.repeat( 299 ) + '👩‍💻' ) ).toBe( 300 );
		expect( countGraphemes( 'a'.repeat( 300 ) + '👩‍💻' ) ).toBe( 301 );
		expect( countGraphemes( '😀'.repeat( 301 ) ) ).toBe( 301 );
	} );

	it( 'handles empty values', () => {
		expect( countGraphemes( '' ) ).toBe( 0 );
		expect( countGraphemes( undefined ) ).toBe( 0 );
	} );

	it( 'falls back to code points without Intl.Segmenter', () => {
		Intl.Segmenter = undefined;
		expect( countGraphemes( '👩‍💻' ) ).toBe( 3 );
		expect( countGraphemes( 'Nueva entrada' ) ).toBe( 13 );
	} );
} );

describe( 'countCodePoints', () => {
	it.each( CASES )(
		'never counts fewer than Intl.Segmenter: %s',
		( name, text ) => {
			expect( countCodePoints( text ) ).toBeGreaterThanOrEqual(
				countGraphemes( text )
			);
		}
	);
} );
