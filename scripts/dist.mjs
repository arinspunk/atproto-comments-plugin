/**
 * Builds the distribution zip: copies the plugin to dist/atproto-comments/,
 * leaving out what .distignore lists, and zips it as
 * dist/atproto-comments-<version>.zip. Run after `npm run build`.
 *
 * Usage: node scripts/dist.mjs
 */
import { execFileSync } from 'node:child_process';
import {
	copyFileSync,
	existsSync,
	mkdirSync,
	readdirSync,
	readFileSync,
	rmSync,
} from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const SLUG = 'atproto-comments';

/**
 * Files that only exist after the build; the zip is useless without them.
 */
export const REQUIRED = [
	'build/editor.js',
	'build/settings.js',
	'assets/vendor/atproto-comments/index.js',
];

/**
 * Parses .distignore.
 *
 * @param {string} text File contents.
 * @return {Array<{anchored: boolean, directory: boolean, pattern: RegExp}>} Rules.
 */
export function parseDistignore( text ) {
	return text
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line !== '' && ! line.startsWith( '#' ) )
		.map( ( line ) => {
			const anchored = line.startsWith( '/' );
			const directory = line.endsWith( '/' );
			const body = line.replace( /^\//, '' ).replace( /\/$/, '' );
			const source = body
				.split( '*' )
				.map( ( part ) => part.replace( /[.+?^${}()|[\]\\]/g, '\\$&' ) )
				.join( '[^/]*' );
			return {
				anchored,
				directory,
				pattern: new RegExp( `^${ source }$` ),
			};
		} );
}

/**
 * Whether a path is left out of the zip.
 *
 * @param {string}  path        Path relative to the repository root, with "/".
 * @param {boolean} isDirectory Whether the path is a directory.
 * @param {Array}   rules       From parseDistignore().
 * @return {boolean} True when ignored.
 */
export function isIgnored( path, isDirectory, rules ) {
	const name = path.split( '/' ).pop();
	return rules.some(
		( rule ) =>
			( ! rule.directory || isDirectory ) &&
			rule.pattern.test( rule.anchored ? path : name )
	);
}

/**
 * Copies a directory recursively, skipping ignored paths.
 *
 * @param {string} root     Repository root.
 * @param {string} relative Directory to copy, relative to root ('' for root).
 * @param {string} target   Destination directory.
 * @param {Array}  rules    From parseDistignore().
 */
function copyTree( root, relative, target, rules ) {
	mkdirSync( target, { recursive: true } );
	for ( const entry of readdirSync( join( root, relative ), {
		withFileTypes: true,
	} ) ) {
		const path = relative ? `${ relative }/${ entry.name }` : entry.name;
		if ( isIgnored( path, entry.isDirectory(), rules ) ) {
			continue;
		}
		if ( entry.isDirectory() ) {
			copyTree( root, path, join( target, entry.name ), rules );
		} else if ( entry.isFile() ) {
			copyFileSync( join( root, path ), join( target, entry.name ) );
		}
	}
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );

	const missing = REQUIRED.filter(
		( file ) => ! existsSync( join( root, file ) )
	);
	if ( missing.length ) {
		console.error(
			`Missing build output, run \`npm run build\` first: ${ missing.join(
				', '
			) }`
		);
		process.exit( 1 );
	}

	const { version } = JSON.parse(
		readFileSync( join( root, 'package.json' ), 'utf8' )
	);
	const rules = parseDistignore(
		readFileSync( join( root, '.distignore' ), 'utf8' )
	);
	const dist = join( root, 'dist' );
	const zip = `${ SLUG }-${ version }.zip`;

	rmSync( dist, { recursive: true, force: true } );
	copyTree( root, '', join( dist, SLUG ), rules );
	execFileSync( 'zip', [ '-qrX', zip, SLUG ], { cwd: dist } );

	console.log( `dist/${ zip }` );
}
