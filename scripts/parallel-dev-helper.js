#!/usr/bin/env node
/**
 * Tiny localhost helper for parallel-dev: lets the WP admin bar inside
 * Playground instances open the current worktree in local macOS apps.
 *
 *   GET /open?app=fork|vscode|zed|iterm|finder&path=/absolute/path&token=<secret>
 *   GET /ping
 *
 * Started by scripts/parallel-dev.sh (helper start) as:
 *   node parallel-dev-helper.js <port> <token> <root> [<root>...]
 *
 * Binds 127.0.0.1 only. Requests must carry the shared token (baked into
 * each instance via the SH_DEV_HELPER_TOKEN constant) so arbitrary web
 * pages can't trigger /open. Paths outside the allowed roots and apps
 * outside the whitelist are rejected; all rejections share one status so
 * responses don't leak filesystem layout.
 */

const http = require( 'http' );
const fs = require( 'fs' );
const path = require( 'path' );
const { execFile } = require( 'child_process' );

const [ portArg, TOKEN, ...rootArgs ] = process.argv.slice( 2 );

const PORT = parseInt( portArg, 10 );

if ( ! PORT || ! TOKEN || rootArgs.length === 0 ) {
	// eslint-disable-next-line no-console
	console.error(
		'Usage: parallel-dev-helper.js <port> <token> <root> [<root>...] — start via scripts/parallel-dev.sh helper start'
	);
	process.exit( 1 );
}

const ROOTS = rootArgs
	.map( ( p ) => {
		try {
			return fs.realpathSync( p );
		} catch ( e ) {
			return null;
		}
	} )
	.filter( Boolean );

const APPS = {
	fork: ( p ) => [ '-a', 'Fork', p ],
	vscode: ( p ) => [ '-a', 'Visual Studio Code', p ],
	zed: ( p ) => [ '-a', 'Zed', p ],
	iterm: ( p ) => [ '-a', 'iTerm', p ],
	finder: ( p ) => [ p ],
};

const deny = ( res ) => {
	res.statusCode = 403;
	res.end( 'denied' );
};

const server = http.createServer( ( req, res ) => {
	const url = new URL( req.url, 'http://127.0.0.1' );

	res.setHeader( 'Access-Control-Allow-Origin', '*' );

	if ( url.pathname === '/ping' ) {
		res.end( 'pong' );
		return;
	}

	if ( url.pathname !== '/open' ) {
		deny( res );
		return;
	}

	const token = url.searchParams.get( 'token' );
	const app = url.searchParams.get( 'app' );
	const target = url.searchParams.get( 'path' );

	if ( token !== TOKEN || ! Object.hasOwn( APPS, app ) || ! target ) {
		deny( res );
		return;
	}

	let real;

	try {
		real = fs.realpathSync( target );
	} catch ( e ) {
		deny( res );
		return;
	}

	const allowed = ROOTS.some(
		( root ) => real === root || real.startsWith( root + path.sep )
	);

	if ( ! allowed ) {
		deny( res );
		return;
	}

	execFile( 'open', APPS[ app ]( real ), ( err ) => {
		if ( err ) {
			// eslint-disable-next-line no-console
			console.error( `open failed for app=${ app }: ${ err.message }` );
		}
	} );

	res.end( 'ok' );
} );

server.listen( PORT, '127.0.0.1', () => {
	// eslint-disable-next-line no-console
	console.log(
		`parallel-dev helper listening on 127.0.0.1:${ PORT }; roots: ${ ROOTS.join(
			', '
		) }`
	);
} );
