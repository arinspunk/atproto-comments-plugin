/**
 * Copies the atproto-comments web component from node_modules into
 * assets/vendor/atproto-comments/ and writes its version to version.php.
 *
 * index.js imports ./element.js. The copy of index.js adds ?ver=<version> to
 * that import, so a plugin update never runs a new index.js with an
 * element.js the browser cached from an older version.
 */
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Modules index.js may import, relative to it. The build fails on any other
 * relative import: it would be a file the plugin does not copy.
 */
export const IMPORTED_FILES = [ 'element.js' ];

const RELATIVE_SPECIFIER = /(["'])(\.\.?\/[^"']*)\1/g;

/**
 * Adds ?ver=<version> to every import of ./element.js in index.js.
 *
 * @param {string} source  Contents of index.js.
 * @param {string} version Component version.
 * @return {string} index.js with the versioned imports.
 * @throws {Error} When index.js does not import ./element.js, or imports any other relative file.
 */
export function versionImports( source, version ) {
	let found = 0;
	const unexpected = [];
	const out = source.replace( RELATIVE_SPECIFIER, ( match, quote, path ) => {
		const file = path.replace( /^\.\//, '' );
		if ( ! IMPORTED_FILES.includes( file ) ) {
			unexpected.push( path );
			return match;
		}
		found++;
		return `${ quote }./${ file }?ver=${ version }${ quote }`;
	} );

	if ( unexpected.length ) {
		throw new Error(
			`index.js imports files the plugin does not copy: ${ unexpected.join(
				', '
			) }. Update scripts/vendor.mjs.`
		);
	}
	if ( ! found ) {
		throw new Error(
			'index.js does not import ./element.js. Update scripts/vendor.mjs.'
		);
	}
	return out;
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
	const pkgDir = join( root, 'node_modules', 'atproto-comments' );
	const outDir = join( root, 'assets', 'vendor', 'atproto-comments' );

	const { version } = JSON.parse(
		readFileSync( join( pkgDir, 'package.json' ), 'utf8' )
	);

	mkdirSync( outDir, { recursive: true } );
	try {
		writeFileSync(
			join( outDir, 'index.js' ),
			versionImports(
				readFileSync( join( pkgDir, 'dist', 'index.js' ), 'utf8' ),
				version
			)
		);
	} catch ( error ) {
		console.error( error.message );
		process.exit( 1 );
	}
	for ( const file of [ ...IMPORTED_FILES, 'atproto-comments.css' ] ) {
		copyFileSync( join( pkgDir, 'dist', file ), join( outDir, file ) );
	}
	writeFileSync(
		join( outDir, 'version.php' ),
		`<?php return ${ JSON.stringify( version ) };\n`
	);

	console.log(
		`atproto-comments ${ version } -> assets/vendor/atproto-comments/`
	);
}
