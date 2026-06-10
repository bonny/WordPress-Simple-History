#!/usr/bin/env node
/**
 * Tiny localhost helper for parallel-dev: lets the WP admin bar inside
 * Playground instances open the current worktree in local macOS apps.
 *
 *   GET /open?app=fork|vscode|iterm|finder&path=/absolute/path
 *   GET /ping
 *
 * Started by scripts/parallel-dev.sh (helper start). Binds 127.0.0.1 only.
 * Allowed roots are passed as CLI arguments; any path outside them is
 * rejected, and apps are limited to the whitelist below.
 */

const http = require( 'http' );
const fs = require( 'fs' );
const path = require( 'path' );
const { execFile } = require( 'child_process' );

const PORT = 9399;

const ROOTS = process.argv
	.slice( 2 )
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
	iterm: ( p ) => [ '-a', 'iTerm', p ],
	finder: ( p ) => [ p ],
};

const server = http.createServer( ( req, res ) => {
	const url = new URL( req.url, 'http://127.0.0.1' );

	res.setHeader( 'Access-Control-Allow-Origin', '*' );

	if ( url.pathname === '/ping' ) {
		res.end( 'pong' );
		return;
	}

	if ( url.pathname !== '/open' ) {
		res.statusCode = 404;
		res.end( 'not found' );
		return;
	}

	const app = url.searchParams.get( 'app' );
	const target = url.searchParams.get( 'path' );

	if ( ! APPS[ app ] || ! target ) {
		res.statusCode = 400;
		res.end( 'bad request' );
		return;
	}

	let real;

	try {
		real = fs.realpathSync( target );
	} catch ( e ) {
		res.statusCode = 404;
		res.end( 'no such path' );
		return;
	}

	const allowed = ROOTS.some(
		( root ) => real === root || real.startsWith( root + path.sep )
	);

	if ( ! allowed ) {
		res.statusCode = 403;
		res.end( 'path not allowed' );
		return;
	}

	execFile( 'open', APPS[ app ]( real ), () => {} );
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
