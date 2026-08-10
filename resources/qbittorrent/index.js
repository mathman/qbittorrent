const express = require('express');
const bodyParser = require('body-parser');
const npid = require('npid');
const passport = require('passport');
const Strategy = require('passport-http-bearer').Strategy;

const ClientManager = require('./lib/ClientManager');
const logger = require('./lib/Logger');

const args = process.argv.slice(2);
const port = args[0];
const apiKey = args[1];

let webServer;
let clientManager;

function asyncHandler(fn) {
	return (req, res, next) => {
		Promise.resolve(fn(req, res, next)).catch((err) => {
			logger.error(`${req.method} ${req.originalUrl} failed: ${err.message}`, {
				stack: err.stack,
				status: err?.response?.status,
			});
			if (!res.headersSent) {
				res.status(err?.response?.status && err.response.status < 500 ? err.response.status : 500);
				res.setHeader('Content-Type', 'application/json');
				res.end(JSON.stringify({ error: err.message || 'Internal error' }));
			}
		});
	};
}

function requestLogger(req, res, next) {
	const start = Date.now();
	res.on('finish', () => {
		const duration = Date.now() - start;
		logger.info(`${req.method} ${req.originalUrl} ${res.statusCode} ${duration}ms`);
	});
	next();
}

let records = [
    { id: 1, username: 'user', token: apiKey, displayName: 'user', emails: [ { value: 'email@email.com' } ] }
];

(async () => {
	
	try {
        var pid = npid.create(args[2]);
        pid.removeOnExit();
    } catch (err) {
        logger.error(`Échec création du pid file: ${err.message}`, { stack: err.stack });
        process.exit(1);
    }
	
	passport.use(new Strategy(
		function(token, cb) {
			for (var i = 0, len = records.length; i < len; i++) {
				var record = records[i];
				if (record.token === token) {
					return cb(null, record);
				}
			}
			return cb(null, false);
		}
	));

    const app = express();
    
    app.use(bodyParser.json());
    app.use(bodyParser.urlencoded({ extended: true }));
    app.use(requestLogger);

	clientManager = new ClientManager();
	
	app.get('/updateClientList',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.updateClientList(req['body']['clients']))));
		})
	)
	.get('/syncMain',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.syncMainData(req['query']['client']))));
		})
	)
	.get('/appVersion',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.getAppVersion(req['query']['client']))));
		})
	)
	.get('/apiVersion',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.getApiVersion(req['query']['client']))));
		})
	)
	.get('/torrentList',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.getTorrentList(req['query']['client'], req['query']['hashes']))));
		})
	)
	.get('/torrentTrackers',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.getTorrentTrackers(req['query']['client'], req['query']['hash']))));
		})
	)
	.get('/torrentContents',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.getTorrentContents(req['query']['client'], req['query']['hash'], req['query']['indexes']))));
		})
	)
	.get('/torrentProperties',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.getTorrentProperties(req['query']['client'], req['query']['hash']))));
		})
	)
	.get('/transfertInfo',
		passport.authenticate('bearer', { session: false }),
		asyncHandler(async (req, res) => {

			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, await clientManager.getTransferInfo(req['query']['client']))));
		})
	)
	.get('/stop', 
		passport.authenticate('bearer', { session: false }), 
		function(req, res) {
			process.exit(0);
		}
	)
    .use(function(req, res, next){
        res.setHeader('Content-Type', 'text/plain');
        res.status(404).send('Page introuvable !');
    });
    
    webServer = app.listen(port, function () {

        logger.info("Api started on port " + port);
    });

	logger.info("Program started");
})();

process.on("SIGINT", async () => {

    if (webServer) {

        webServer.close(() => {

            logger.info('Http server closed.');
        });
    }

	if (clientManager) {

		await clientManager.closeAllConnections();
	}

    process.removeAllListeners("SIGINT");
});

process.on('uncaughtException', (err) => {
    logger.error(`Uncaught exception: ${err.message}`, { stack: err.stack });
});

process.on('unhandledRejection', (reason) => {
    const err = reason instanceof Error ? reason : new Error(String(reason));
    logger.error(`Unhandled rejection: ${err.message}`, { stack: err.stack });
});