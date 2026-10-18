// A minimal realtime gateway fed by the "webhook" driver.
//
//   npm i express ws
//   MESSAGE_BUS_WEBHOOK_SECRET=s3cret node server.mjs
//
// Browsers connect to ws://localhost:6002?channels=orders,news and receive
// {channel, event, payload} frames. (Authorize private channels before
// subscribing in a real app — e.g. with a signed token issued by Laravel.)
import crypto from 'node:crypto';
import http from 'node:http';
import express from 'express';
import { WebSocketServer } from 'ws';

const SECRET = process.env.MESSAGE_BUS_WEBHOOK_SECRET ?? '';
const PORT = Number(process.env.PORT ?? 6002);

const app = express();
const server = http.createServer(app);
const wss = new WebSocketServer({ server });

/** @type {Map<string, Set<import('ws').WebSocket>>} */
const channels = new Map();

wss.on('connection', (socket, request) => {
  const url = new URL(request.url, 'http://localhost');
  const names = (url.searchParams.get('channels') ?? '').split(',').filter(Boolean);

  for (const name of names) {
    if (!channels.has(name)) channels.set(name, new Set());
    channels.get(name).add(socket);
  }

  socket.on('close', () => names.forEach((name) => channels.get(name)?.delete(socket)));
});

function verify(rawBody, signature) {
  if (!SECRET) return true;
  const expected = 'sha256=' + crypto.createHmac('sha256', SECRET).update(rawBody).digest('hex');
  if (!signature || signature.length !== expected.length) return false;
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature));
}

// Keep the raw body: the signature is computed over the exact bytes.
app.post('/hook', express.raw({ type: 'application/json' }), (req, res) => {
  if (!verify(req.body, req.get('X-Message-Bus-Signature'))) {
    return res.status(401).json({ error: 'invalid signature' });
  }

  const { channels: targets, event, payload } = JSON.parse(req.body.toString('utf8'));
  let delivered = 0;

  for (const channel of targets) {
    for (const socket of channels.get(channel) ?? []) {
      socket.send(JSON.stringify({ channel, event, payload }));
      delivered++;
    }
  }

  res.json({ delivered });
});

server.listen(PORT, () => console.log(`realtime gateway listening on :${PORT}`));
