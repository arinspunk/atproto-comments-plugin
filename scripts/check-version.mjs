/**
 * Checks that the plugin version is the same everywhere it is written and,
 * given a tag (vX.Y.Z), that it matches the tag.
 *
 * Usage: node scripts/check-version.mjs [vX.Y.Z]
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const TAG_PATTERN = /^v(\d+\.\d+\.\d+)$/;

/**
 * Extracts every version from the contents of the files that hold one.
 *
 * @param {Object} files             File contents.
 * @param {string} files.plugin      atproto-comments.php.
 * @param {string} files.readme      readme.txt.
 * @param {string} files.packageJson package.json.
 * @param {string} files.packageLock package-lock.json.
 * @return {Array<{source: string, version: (string|null)}>} One entry per place; null when it is missing.
 */
export function readVersions( { plugin, readme, packageJson, packageLock } ) {
	const match = ( text, pattern ) => {
		const found = text.match( pattern );
		return found ? found[ 1 ] : null;
	};
	const pkg = JSON.parse( packageJson );
	const lock = JSON.parse( packageLock );

	return [
		{
			source: 'atproto-comments.php (Version header)',
			version: match( plugin, /^[ \t/*]*Version:[ \t]*(\S+)/m ),
		},
		{
			source: 'atproto-comments.php (ATPROTO_COMMENTS_VERSION)',
			version: match(
				plugin,
				/define\(\s*'ATPROTO_COMMENTS_VERSION',\s*'([^']+)'\s*\)/
			),
		},
		{
			source: 'readme.txt (Stable tag)',
			version: match( readme, /^Stable tag:[ \t]*(\S+)/m ),
		},
		{ source: 'package.json (version)', version: pkg.version ?? null },
		{
			source: 'package-lock.json (version)',
			version: lock.version ?? null,
		},
		{
			source: 'package-lock.json (packages[""].version)',
			version: lock.packages?.[ '' ]?.version ?? null,
		},
	];
}

/**
 * Compares the versions with each other and, optionally, with a tag.
 *
 * Without a tag, package.json is the reference.
 *
 * @param {Array<{source: string, version: (string|null)}>} versions From readVersions().
 * @param {string}                                          [tag]    Release tag, vX.Y.Z.
 * @return {string[]} Errors; empty when everything matches.
 */
export function checkVersions( versions, tag ) {
	let expected;
	let reference;

	if ( tag !== undefined ) {
		const found = tag.match( TAG_PATTERN );
		if ( ! found ) {
			return [ `Invalid tag "${ tag }": expected vX.Y.Z.` ];
		}
		expected = found[ 1 ];
		reference = `tag ${ tag }`;
	} else {
		expected = versions.find(
			( entry ) => entry.source === 'package.json (version)'
		)?.version;
		reference = 'package.json';
	}

	return versions
		.filter( ( entry ) => entry.version !== expected )
		.map( ( entry ) =>
			entry.version === null
				? `${ entry.source }: version not found.`
				: `${ entry.source }: ${ entry.version } does not match ${ expected } (${ reference }).`
		);
}

/**
 * Reads the files from the repository root.
 *
 * @param {string} root Repository root.
 * @return {Object} File contents for readVersions().
 */
export function readFiles( root ) {
	const read = ( name ) => readFileSync( join( root, name ), 'utf8' );
	return {
		plugin: read( 'atproto-comments.php' ),
		readme: read( 'readme.txt' ),
		packageJson: read( 'package.json' ),
		packageLock: read( 'package-lock.json' ),
	};
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
	const tag = process.argv[ 2 ];
	const errors = checkVersions( readVersions( readFiles( root ) ), tag );

	if ( errors.length ) {
		console.error( 'Version mismatch:' );
		errors.forEach( ( error ) => console.error( `  - ${ error }` ) );
		process.exit( 1 );
	}
	const { version } = JSON.parse(
		readFileSync( join( root, 'package.json' ), 'utf8' )
	);
	console.log(
		tag
			? `Version ${ version } matches ${ tag }.`
			: `Version ${ version } is consistent.`
	);
}
