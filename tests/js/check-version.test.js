/**
 * Version consistency check used by the CI and the release workflow.
 */
import { describe, expect, it } from 'vitest';
import { checkVersions, readVersions } from '../../scripts/check-version.mjs';

const files = ( versions = {} ) => {
	const v = {
		header: '1.2.3',
		constant: '1.2.3',
		stable: '1.2.3',
		pkg: '1.2.3',
		lock: '1.2.3',
		lockRoot: '1.2.3',
		...versions,
	};
	return {
		plugin: `<?php\n/**\n * Plugin Name: Parolas – Comments with atproto\n * Version:           ${ v.header }\n */\ndefine( 'ATPROTO_COMMENTS_VERSION', '${ v.constant }' );\n`,
		readme: `=== Parolas – Comments with atproto ===\nRequires PHP: 7.4\nStable tag: ${ v.stable }\n`,
		packageJson: JSON.stringify( { name: 'p', version: v.pkg } ),
		packageLock: JSON.stringify( {
			name: 'p',
			version: v.lock,
			packages: { '': { name: 'p', version: v.lockRoot } },
		} ),
	};
};

const check = ( versions, tag ) =>
	checkVersions( readVersions( files( versions ) ), tag );

describe( 'readVersions', () => {
	it( 'reads the six places', () => {
		expect(
			readVersions( files() ).map( ( entry ) => entry.version )
		).toEqual( [ '1.2.3', '1.2.3', '1.2.3', '1.2.3', '1.2.3', '1.2.3' ] );
	} );

	it( 'returns null for a missing version', () => {
		const input = files();
		input.readme = '=== Parolas – Comments with atproto ===\n';
		expect( readVersions( input )[ 2 ].version ).toBeNull();
	} );
} );

describe( 'checkVersions', () => {
	it( 'passes when everything matches', () => {
		expect( check() ).toEqual( [] );
	} );

	it( 'passes when everything matches the tag', () => {
		expect( check( {}, 'v1.2.3' ) ).toEqual( [] );
	} );

	it.each( [
		[ 'header', 'parolas.php (Version header)' ],
		[ 'constant', 'parolas.php (ATPROTO_COMMENTS_VERSION)' ],
		[ 'stable', 'readme.txt (Stable tag)' ],
		[ 'lock', 'package-lock.json (version)' ],
		[ 'lockRoot', 'package-lock.json (packages[""].version)' ],
	] )( 'reports %s out of sync', ( key, source ) => {
		const errors = check( { [ key ]: '1.2.4' } );
		expect( errors ).toHaveLength( 1 );
		expect( errors[ 0 ] ).toContain( source );
		expect( errors[ 0 ] ).toContain( '1.2.4' );
	} );

	it( 'reports every place except package.json when package.json is out of sync', () => {
		const errors = check( { pkg: '1.2.4' } );
		expect( errors ).toHaveLength( 5 );
	} );

	it( 'reports package.json against the tag', () => {
		const errors = check( { pkg: '1.2.4' }, 'v1.2.3' );
		expect( errors ).toHaveLength( 1 );
		expect( errors[ 0 ] ).toContain( 'package.json (version)' );
	} );

	it( 'reports every place when the tag is different', () => {
		const errors = check( {}, 'v9.9.9' );
		expect( errors ).toHaveLength( 6 );
		expect( errors[ 0 ] ).toContain( 'tag v9.9.9' );
	} );

	it.each( [ '1.2.3', 'v1.2', 'v1.2.3-beta', 'release-1.2.3' ] )(
		'rejects the invalid tag %s',
		( tag ) => {
			const errors = check( {}, tag );
			expect( errors ).toHaveLength( 1 );
			expect( errors[ 0 ] ).toContain( 'Invalid tag' );
		}
	);

	it( 'reports a missing version', () => {
		const versions = readVersions( files() );
		versions[ 2 ].version = null;
		expect( checkVersions( versions ) ).toEqual( [
			'readme.txt (Stable tag): version not found.',
		] );
	} );
} );
