/**
 * Copies the atproto-comments web component from node_modules into
 * assets/vendor/atproto-comments/ and writes its version to version.php.
 */
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const pkgDir = join( root, 'node_modules', 'atproto-comments' );
const outDir = join( root, 'assets', 'vendor', 'atproto-comments' );

const { version } = JSON.parse(
	readFileSync( join( pkgDir, 'package.json' ), 'utf8' )
);

mkdirSync( outDir, { recursive: true } );
for ( const file of [ 'index.js', 'atproto-comments.css' ] ) {
	copyFileSync( join( pkgDir, 'dist', file ), join( outDir, file ) );
}
writeFileSync(
	join( outDir, 'version.php' ),
	`<?php return ${ JSON.stringify( version ) };\n`
);

console.log( `atproto-comments ${ version } -> assets/vendor/atproto-comments/` );
