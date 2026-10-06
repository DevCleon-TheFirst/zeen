// Prevent uncaught errors or stream resets from killing the gateway
process.on('uncaughtException', (err) => {
    console.error('[Gateway UncaughtException]', err?.message || err);
});
process.on('unhandledRejection', (reason) => {
    console.error('[Gateway UnhandledRejection]', reason?.message || reason);
});

const express = require('express');
const cors = require('cors');
const QRCode = require('qrcode');
const pino = require('pino');
const path = require('path');
const fs = require('fs');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore,
} = require('@whiskeysockets/baileys');

const app = express();
app.use(cors());
app.use(express.json());

const PORT = process.env.PORT || 3001;
const LARAVEL_WEBHOOK_BASE = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8000/api/webhooks/whatsapp-qr';

const logger = pino({ level: 'silent' });

// Store active sessions in memory
// sessions[businessId] = { sock, qr, status: 'DISCONNECTED'|'SCAN_QR'|'CONNECTED', user: null, saveCreds }
const sessions = {};

// Ensure session storage directory exists
const SESSIONS_DIR = path.join(__dirname, 'sessions');
if (!fs.existsSync(SESSIONS_DIR)) {
    fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}

async function startSession(businessId) {
    if (sessions[businessId] && sessions[businessId].status === 'CONNECTED') {
        return sessions[businessId];
    }

    const sessionDir = path.join(SESSIONS_DIR, `session_${businessId}`);
    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
    const { version } = await fetchLatestBaileysVersion();

    // In-memory message store so Baileys can handle retransmission requests
    // from the phone — this is what makes sent messages appear on your phone
    const msgStore = {};

    const sock = makeWASocket({
        version,
        logger,
        printQRInTerminal: false,
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, logger),
        },
        generateHighQualityLinkPreview: false,
        markOnlineOnConnect: false,   // Don't override phone's online status
        syncFullHistory: false,        // Don't re-download full history
        // Required for the phone to receive sent-message echoes:
        getMessage: async (key) => {
            const id = key?.id;
            if (id && msgStore[id]) return msgStore[id];
            return undefined;
        },
    });

    sessions[businessId] = {
        sock,
        qr: null,
        status: state.creds.me ? 'CONNECTED' : 'INITIALIZING',
        user: state.creds.me ? state.creds.me.id.split(':')[0] : null,
        saveCreds,
        msgStore, // shared with getMessage callback above
        botSentMessageIds: new Set(),
    };

    sock.ev.on('creds.update', saveCreds);

    // Cache ALL messages (inbound & outbound) so the phone can request
    // retransmission and see them in its chat list
    sock.ev.on('messages.upsert', ({ messages }) => {
        for (const msg of messages) {
            if (msg.key?.id && msg.message) {
                msgStore[msg.key.id] = msg.message;
            }
        }
    });

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            try {
                const qrImage = await QRCode.toDataURL(qr);
                sessions[businessId].qr = qrImage;
                sessions[businessId].status = 'SCAN_QR';
                console.log(`[Business ${businessId}] New QR Code generated.`);
            } catch (err) {
                console.error(`QR generation error for business ${businessId}:`, err);
            }
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

            console.log(`[Business ${businessId}] Connection closed. Reason: ${statusCode}, Reconnecting: ${shouldReconnect}`);

            sessions[businessId].status = 'DISCONNECTED';
            sessions[businessId].qr = null;

            if (shouldReconnect) {
                setTimeout(() => startSession(businessId), 3000);
            } else {
                // Session logged out - clean up files
                delete sessions[businessId];
                if (fs.existsSync(sessionDir)) {
                    fs.rmSync(sessionDir, { recursive: true, force: true });
                }
            }
        } else if (connection === 'open') {
            const userPhone = sock.user ? sock.user.id.split(':')[0] : 'Connected';
            sessions[businessId].status = 'CONNECTED';
            sessions[businessId].qr = null;
            sessions[businessId].user = userPhone;
            console.log(`[Business ${businessId}] WhatsApp connected successfully! Phone: ${userPhone}`);
        }
    });

    // Track delivery receipts (ACKs) - notify Laravel when messages are delivered/read
    sock.ev.on('messages.update', async (updates) => {
        for (const update of updates) {
            if (!update.key.fromMe) continue; // Only care about our sent messages

            const ack = update.update?.status;
            // ACK levels: 1=sent to server, 2=delivered to device, 3=read, 4=played (voice)
            if (ack !== undefined && ack >= 2) {
                const messageId = update.key.id;
                const statusLabel = ack >= 3 ? 'read' : 'delivered';

                const webhookUrl = `${LARAVEL_WEBHOOK_BASE}/${businessId}/ack`;
                try {
                    const ackRes = await fetch(webhookUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ message_id: messageId, status: statusLabel }),
                    });
                    await ackRes.text().catch(() => {});
                } catch (e) {
                    // Non-critical, ignore
                }
            }
        }
    });

    // Handle Incoming Messages & Human Owner Takeover
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            if (!msg.message) continue;

            const isFromMe = !!msg.key.fromMe;
            const messageId = msg.key.id;

            // Cache in store so getMessage callback can serve it if needed
            if (messageId && msg.message) {
                msgStore[messageId] = msg.message;
            }

            // If this message was sent by our automated bot/API, skip it
            if (isFromMe && sessions[businessId]?.botSentMessageIds?.has(messageId)) {
                continue;
            }

            const senderJid = msg.key.remoteJid;
            if (!senderJid) continue;
            // Ignore group chats, status broadcasts, and newsletters
            if (senderJid.endsWith('@g.us') || senderJid === 'status@broadcast' || senderJid.endsWith('@newsletter')) continue;

            let targetPhone = null;
            let targetLid = null;

            if (senderJid.endsWith('@s.whatsapp.net')) {
                targetPhone = senderJid.replace('@s.whatsapp.net', '').replace(/[^0-9]/g, '');
            } else if (senderJid.endsWith('@lid')) {
                targetLid = senderJid.replace('@lid', '').replace(/[^0-9]/g, '');
                // Check if reverse LID mapping exists on disk
                const revPath = path.join(sessionDir, `lid-mapping-${targetLid}_reverse.json`);
                if (fs.existsSync(revPath)) {
                    try {
                        targetPhone = JSON.parse(fs.readFileSync(revPath, 'utf8'));
                    } catch (e) {}
                }
                if (!targetPhone) {
                    targetPhone = targetLid;
                }
            } else {
                continue;
            }

            // Extract text message content or media caption
            const text = msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                msg.message.imageMessage?.caption ||
                msg.message.videoMessage?.caption ||
                msg.message.documentMessage?.caption ||
                '';

            if (isFromMe) {
                // Owner replied directly from their WhatsApp app on their phone or desktop!
                console.log(`[Business ${businessId}] [HumanTakeover] Owner sent message to ${targetPhone}: "${text || '[Media]'}"`);

                const ownerPayload = {
                    event: 'owner_message',
                    business_id: businessId,
                    to: targetPhone,
                    text: text || '[Media]',
                    message_id: messageId,
                    timestamp: msg.messageTimestamp,
                };

                try {
                    const ownerRes = await fetch(`${LARAVEL_WEBHOOK_BASE}/${businessId}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(ownerPayload),
                    });
                    await ownerRes.text().catch(() => {});
                } catch (error) {
                    console.error(`[Business ${businessId}] Failed to send owner takeover webhook to Laravel:`, error.message);
                }
                continue;
            }

            // Customer inbound message
            const senderName = msg.pushName || `WhatsApp ${targetPhone.slice(-4)}`;
            if (!text) continue;

            const payload = {
                event: 'message',
                business_id: businessId,
                from: targetPhone,
                name: senderName,
                text: text,
                message_id: messageId,
                timestamp: msg.messageTimestamp,
            };

            console.log(`[Business ${businessId}] Inbound WhatsApp from ${targetPhone}: "${text}"`);

            // Dispatch payload to Laravel Backend
            try {
                const response = await fetch(`${LARAVEL_WEBHOOK_BASE}/${businessId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                await response.text().catch(() => {});
                console.log(`[Business ${businessId}] Webhook response status: ${response.status}`);
            } catch (error) {
                console.error(`[Business ${businessId}] Failed to send webhook to Laravel:`, error.message);
            }
        }
    });

    return sessions[businessId];
}

// REST APIs for Laravel

// Get instance status / QR Code
app.get('/status/:businessId', async (req, res) => {
    const { businessId } = req.params;

    if (!sessions[businessId]) {
        await startSession(businessId);
    }

    const s = sessions[businessId] || { status: 'DISCONNECTED', qr: null, user: null };
    res.json({
        status: s.status,
        qr: s.qr,
        user: s.user,
    });
});

// Force connect / generate new QR Code
app.post('/connect/:businessId', async (req, res) => {
    const { businessId } = req.params;
    const session = await startSession(businessId);
    res.json({
        status: session.status,
        qr: session.qr,
        user: session.user,
    });
});

// Request 8-digit Pairing Code via phone number (No camera / QR scan needed)
app.post('/pairing-code/:businessId', async (req, res) => {
    const { businessId } = req.params;
    const { phone } = req.body;

    if (!phone) {
        return res.status(400).json({ error: 'Missing phone number' });
    }

    const cleanPhone = phone.replace(/[^0-9]/g, '');
    let session = sessions[businessId];
    if (!session || !session.sock) {
        session = await startSession(businessId);
    }

    try {
        setTimeout(async () => {
            try {
                const code = await session.sock.requestPairingCode(cleanPhone);
                console.log(`[Business ${businessId}] Pairing code generated for ${cleanPhone}: ${code}`);
                res.json({ success: true, code });
            } catch (err) {
                console.error(`[Business ${businessId}] Pairing code error:`, err.message);
                res.status(500).json({ error: err.message || 'Failed to generate pairing code' });
            }
        }, 1500);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Send outbound message via Baileys socket
app.post('/send/:businessId', async (req, res) => {
    const { businessId } = req.params;
    const { to, text, mediaUrl } = req.body;

    if (!to || (!text && !mediaUrl)) {
        return res.status(400).json({ error: 'Missing "to" or "text" parameters' });
    }

    const session = sessions[businessId];
    if (!session || session.status !== 'CONNECTED' || !session.sock) {
        return res.status(400).json({ error: 'WhatsApp instance is not connected. Please scan QR code first.' });
    }

    try {
        const cleanPhone = to.replace(/[^0-9]/g, '');
        let targetPhone = cleanPhone;

        // Check if cleanPhone is an LID (has a reverse mapping to a real phone number)
        const sessionDir = path.join(SESSIONS_DIR, `session_${businessId}`);
        const revPath = path.join(sessionDir, `lid-mapping-${cleanPhone}_reverse.json`);
        if (fs.existsSync(revPath)) {
            try {
                const resolved = JSON.parse(fs.readFileSync(revPath, 'utf8'));
                if (resolved) {
                    console.log(`[Business ${businessId}] Resolved LID ${cleanPhone} to phone ${resolved}`);
                    targetPhone = resolved;
                }
            } catch (e) {}
        }

        const recipientJid = `${targetPhone}@s.whatsapp.net`;

        // Attempt to verify the number is on WhatsApp (non-blocking if check fails)
        try {
            const results = await session.sock.onWhatsApp(targetPhone);
            const result = results && results[0];
            if (result && result.exists === false) {
                console.warn(`[Business ${businessId}] Number ${targetPhone} is NOT on WhatsApp.`);
                return res.status(422).json({
                    success: false,
                    error: `The number +${targetPhone} is not registered on WhatsApp.`,
                    not_on_whatsapp: true,
                });
            }
        } catch (checkErr) {
            // onWhatsApp lookup failed — proceed with send anyway, Baileys will handle it
            console.warn(`[Business ${businessId}] onWhatsApp check failed for ${targetPhone}: ${checkErr.message}`);
        }

        // Check if mediaUrl points to a local file in storage
        let localMediaPath = null;
        if (mediaUrl) {
            if (mediaUrl.includes('/storage/catalog/')) {
                const filename = path.basename(mediaUrl.split('?')[0]);
                const candidate = path.join(__dirname, '..', 'storage', 'app', 'public', 'catalog', filename);
                if (fs.existsSync(candidate)) {
                    localMediaPath = candidate;
                }
            } else if (mediaUrl.includes('/storage/')) {
                const relative = mediaUrl.split('/storage/')[1].split('?')[0];
                const candidate = path.join(__dirname, '..', 'storage', 'app', 'public', relative);
                if (fs.existsSync(candidate)) {
                    localMediaPath = candidate;
                }
            }
        }

        let sentMsg;
        if (localMediaPath) {
            console.log(`[Business ${businessId}] Sending WhatsApp image from local storage: ${localMediaPath}`);
            sentMsg = await session.sock.sendMessage(recipientJid, {
                image: fs.readFileSync(localMediaPath),
                caption: text || '',
            });
        } else if (mediaUrl && (mediaUrl.startsWith('http://') || mediaUrl.startsWith('https://')) && !mediaUrl.includes('localhost') && !mediaUrl.includes('127.0.0.1')) {
            console.log(`[Business ${businessId}] Sending WhatsApp image from URL: ${mediaUrl}`);
            sentMsg = await session.sock.sendMessage(recipientJid, {
                image: { url: mediaUrl },
                caption: text || '',
            });
        } else {
            sentMsg = await session.sock.sendMessage(recipientJid, { text: text || '' });
        }

        // Cache in store and register ID in botSentMessageIds so we don't treat our own echo as human takeover
        if (sentMsg?.key?.id) {
            session.botSentMessageIds = session.botSentMessageIds || new Set();
            session.botSentMessageIds.add(sentMsg.key.id);
            if (session.botSentMessageIds.size > 5000) {
                const first = session.botSentMessageIds.values().next().value;
                session.botSentMessageIds.delete(first);
            }
            if (sentMsg.message) {
                session.msgStore[sentMsg.key.id] = sentMsg.message;
            }
        }

        console.log(`[Business ${businessId}] Outbound message sent to ${targetPhone} (${recipientJid}) [id: ${sentMsg?.key?.id}]`);

        res.json({
            success: true,
            message_id: sentMsg?.key?.id,
        });
    } catch (err) {
        console.error(`[Business ${businessId}] Error sending outbound WhatsApp:`, err.message);
        res.status(500).json({ error: err.message });
    }
});

// Check if a phone number is registered on WhatsApp
app.get('/check/:businessId/:phone', async (req, res) => {
    const { businessId, phone } = req.params;
    const session = sessions[businessId];

    if (!session || session.status !== 'CONNECTED' || !session.sock) {
        return res.status(400).json({ error: 'Not connected' });
    }

    try {
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        let targetPhone = cleanPhone;

        const sessionDir = path.join(SESSIONS_DIR, `session_${businessId}`);
        const revPath = path.join(sessionDir, `lid-mapping-${cleanPhone}_reverse.json`);
        if (fs.existsSync(revPath)) {
            try {
                const resolved = JSON.parse(fs.readFileSync(revPath, 'utf8'));
                if (resolved) targetPhone = resolved;
            } catch (e) {}
        }

        const [result] = await session.sock.onWhatsApp(targetPhone);
        res.json({ phone: targetPhone, original: cleanPhone, exists: !!(result?.exists), jid: result?.jid || null });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Logout / Disconnect session
app.post('/disconnect/:businessId', async (req, res) => {
    const { businessId } = req.params;
    const session = sessions[businessId];

    if (session && session.sock) {
        try {
            await session.sock.logout();
        } catch (e) {}
    }

    delete sessions[businessId];
    const sessionDir = path.join(SESSIONS_DIR, `session_${businessId}`);
    if (fs.existsSync(sessionDir)) {
        fs.rmSync(sessionDir, { recursive: true, force: true });
    }

    console.log(`[Business ${businessId}] Disconnected and session deleted.`);
    res.json({ success: true, status: 'DISCONNECTED' });
});

app.listen(PORT, () => {
    console.log(`=================================================`);
    console.log(`🚀 WhatsApp Web QR Gateway running on port ${PORT}`);
    console.log(`=================================================`);

    // Auto-restore saved sessions on gateway boot
    try {
        if (fs.existsSync(SESSIONS_DIR)) {
            const dirs = fs.readdirSync(SESSIONS_DIR);
            for (const dir of dirs) {
                if (dir.startsWith('session_')) {
                    const bizId = dir.replace('session_', '');
                    console.log(`[Auto-Boot] Restoring WhatsApp session for business ${bizId}...`);
                    startSession(bizId).catch(err => {
                        console.error(`[Auto-Boot] Error restoring session ${bizId}:`, err?.message || err);
                    });
                }
            }
        }
    } catch (e) {
        console.error('[Auto-Boot] Error scanning sessions directory:', e.message);
    }
});

