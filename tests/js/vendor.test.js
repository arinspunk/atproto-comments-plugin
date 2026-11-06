/**
 * Rewriting of the component's index.js when it is copied to the plugin.
 */
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { versionImports } from '../../scripts/vendor.mjs';

const INDEX_2_0_0 = readFileSync(
	new URL( '../fixtures/atproto-comments-2.0.0-index.js', import.meta.url ),
	'utf8'
);

describe( 'versionImports', () => {
	it( 'versions both statements of the 2.0.0 index.js with the same URL', () => {
		const out = versionImports( INDEX_2_0_0, '2.0.0' );
		expect( out ).toContain(
			'import { AtprotoComments } from "./element.js?ver=2.0.0";'
		);
		expect( out ).toContain( 'export * from "./element.js?ver=2.0.0";' );
		expect( out.match( /\.\/element\.js(\?ver=[^"]*)?"/g ) ).toEqual( [
			'./element.js?ver=2.0.0"',
			'./element.js?ver=2.0.0"',
		] );
	} );

	it( 'leaves everything else untouched', () => {
		const out = versionImports( INDEX_2_0_0, '2.0.0' );
		expect( out.replaceAll( '?ver=2.0.0', '' ) ).toBe( INDEX_2_0_0 );
	} );

	it( 'accepts single quotes', () => {
		expect( versionImports( "import './element.js';", '2.1.0' ) ).toBe(
			"import './element.js?ver=2.1.0';"
		);
	} );

	it( 'throws when index.js does not import ./element.js', () => {
		expect( () =>
			versionImports( 'customElements.define("x", class {});', '2.0.0' )
		).toThrow( /does not import \.\/element\.js/ );
	} );

	it( 'throws on a relative import of another file', () => {
		expect( () =>
			versionImports(
				'import "./element.js";\nimport "./apps.js";',
				'2.0.0'
			)
		).toThrow( /\.\/apps\.js/ );
	} );

	it( 'throws on a parent-relative import', () => {
		expect( () =>
			versionImports(
				'import "./element.js";\nimport "../shared.js";',
				'2.0.0'
			)
		).toThrow( /\.\.\/shared\.js/ );
	} );
} );
