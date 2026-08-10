const path = require('path');
const winston = require('winston');
require('winston-daily-rotate-file');

const LOG_DIR = path.join(__dirname, '..', 'log');

const fileFormat = winston.format.combine(
    winston.format.timestamp(),
    winston.format.errors({ stack: true }),
    winston.format.printf(({ timestamp, level, message, stack, ...meta }) => {
        const metaStr = Object.keys(meta).length ? ` ${JSON.stringify(meta)}` : '';
        return `${timestamp} [${level.toUpperCase()}] ${stack || message}${metaStr}`;
    })
);

const logger = winston.createLogger({
    level: 'info',
    format: fileFormat,
    transports: [
        new winston.transports.DailyRotateFile({
            dirname: LOG_DIR,
            filename: 'app-%DATE%.log',
            datePattern: 'YYYY-MM-DD',
            maxFiles: '14d',
        }),
        new winston.transports.DailyRotateFile({
            dirname: LOG_DIR,
            filename: 'error-%DATE%.log',
            datePattern: 'YYYY-MM-DD',
            maxFiles: '14d',
            level: 'error',
        }),
        new winston.transports.Console({
            format: winston.format.combine(winston.format.colorize(), fileFormat),
        }),
    ],
});

module.exports = logger;
