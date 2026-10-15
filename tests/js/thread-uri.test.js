/**
 * Runs the shared fixture through the JS implementation.
 */
import { describe, expect, it } from 'vitest';
import { normalize } from '../../src/editor/thread-uri';
import fixture from '../fixtures/thread-uris.json';

describe( 'normalize', () => {
	it.each( fixture.cases.map( ( c ) => [ c.name, c.input, c.expected ] ) )(
		'%s',
		( name, input, expected ) => {
			expect( normalize( input ) ).toEqual( expected );
		}
	);
} );
