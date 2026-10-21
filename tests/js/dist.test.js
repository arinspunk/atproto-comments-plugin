/**
 * .distignore parsing and matching used to build the distribution zip.
 */
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { isIgnored, parseDistignore } from '../../scripts/dist.mjs';

const ignored = ( text, path, isDirectory = false ) =>
	isIgnored( path, isDirectory, parseDistignore( text ) );

describe( 'parseDistignore', () => {
	it( 'skips comments and blank lines', () => {
		expect(
			parseDistignore( '# comment\n\n  \n/src/\n  # indented\n' )
		).toHaveLength( 1 );
	} );

	it( 'reads anchored and directory flags', () => {
		const [ rule ] = parseDistignore( '/src/' );
		expect( rule.anchored ).toBe( true );
		expect( rule.directory ).toBe( true );
	} );

	it( 'accepts Windows line endings', () => {
		expect( parseDistignore( '/src/\r\n/tests/\r\n' ) ).toHaveLength( 2 );
	} );
} );

describe( 'isIgnored', () => {
	it( 'matches an anchored directory only at the root', () => {
		expect( ignored( '/vendor/', 'vendor', true ) ).toBe( true );
		expect( ignored( '/vendor/', 'assets/vendor', true ) ).toBe( false );
	} );

	it( 'matches an unanchored name at any depth', () => {
		expect( ignored( '.DS_Store', '.DS_Store' ) ).toBe( true );
		expect( ignored( '.DS_Store', 'assets/vendor/.DS_Store' ) ).toBe(
			true
		);
	} );

	it( 'matches a directory entry only against directories', () => {
		expect( ignored( '/build/', 'build', false ) ).toBe( false );
		expect( ignored( '/build/', 'build', true ) ).toBe( true );
	} );

	it( 'matches files and directories with an entry without a trailing slash', () => {
		expect( ignored( '/.git*', '.gitignore' ) ).toBe( true );
		expect( ignored( '/.git*', '.github', true ) ).toBe( true );
	} );

	it( 'expands * within a name only', () => {
		expect( ignored( '/package*.json', 'package-lock.json' ) ).toBe( true );
		expect( ignored( '/package*.json', 'package.json' ) ).toBe( true );
		expect( ignored( '/package*.json', 'package/sub.json' ) ).toBe( false );
		expect( ignored( '/composer.*', 'composer.lock' ) ).toBe( true );
	} );

	it( 'treats dots literally', () => {
		expect( ignored( '/README.md', 'READMEXmd' ) ).toBe( false );
	} );

	it( 'does not match a prefix of a name', () => {
		expect( ignored( '/src/', 'srcs', true ) ).toBe( false );
	} );
} );

describe( 'the repository .distignore', () => {
	const text = readFileSync(
		new URL( '../../.distignore', import.meta.url ),
		'utf8'
	);

	it.each( [
		[ 'src', true ],
		[ 'tests', true ],
		[ 'vendor', true ],
		[ 'node_modules', true ],
		[ 'openspec', true ],
		[ 'dist', true ],
		[ '.wordpress-org', true ],
		[ 'package.json', false ],
		[ '.wp-env.override.json', false ],
		[ 'README.md', false ],
	] )( 'leaves out %s', ( path, isDirectory ) => {
		expect( ignored( text, path, isDirectory ) ).toBe( true );
	} );

	it.each( [
		[ 'assets', true ],
		[ 'assets/vendor', true ],
		[ 'build', true ],
		[ 'includes', true ],
		[ 'languages', true ],
		[ 'templates', true ],
		[ 'parolas.php', false ],
		[ 'uninstall.php', false ],
		[ 'readme.txt', false ],
	] )( 'keeps %s', ( path, isDirectory ) => {
		expect( ignored( text, path, isDirectory ) ).toBe( false );
	} );
} );
