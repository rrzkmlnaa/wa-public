const { Client, LocalAuth } = require("whatsapp-web.js");
const qrcode = require("qrcode-terminal");
const express = require("express");
const axios = require("axios");

const app = express();
const port = 3001;

app.use(express.json());

const clients = {}; // key: clientId, value: { client, qr, ready }

function initClient(clientId) {
    if (clients[clientId]) {
        console.log(`Client ${clientId} already exists.`);
        return;
    }

    const client = new Client({
        authStrategy: new LocalAuth({ clientId }),
        puppeteer: { headless: true },
    });

    clients[clientId] = { client, qr: null, ready: false };

    client.on("qr", (qr) => {
        console.log(`QR for ${clientId} received.`);
        clients[clientId].qr = qr;
        // qrcode.generate(qr, { small: true });
    });

    client.on("ready", async () => {
        console.log(`Client ${clientId} is ready.`);
        clients[clientId].ready = true;
        clients[clientId].qr = null;

        // Notify Laravel API (optional)
        axios
            .post("http://127.0.0.1:8000/api/device-connected", {
                client_id: clientId,
            })
            .catch((err) =>
                console.error("Failed to notify Laravel:", err.message)
            );

        axios
            .post("http://127.0.0.1:8000/api/sync-contacts/" + clientId)
            .then(() => console.log("Contacts synced."))
            .catch((err) =>
                console.error("Sync contacts failed:", err.message)
            );

        await InitAllMessages(client, clientId);
    });

    client.on("message", async (msg) => {
        try {
            const chat = await msg.getChat(); // dapat chat yang terkait dengan pesan ini
            const allMessages = await chat.fetchMessages({ limit: 100 }); // ambil sampai 100 pesan terakhir

            // Kirim ke backend misal
            logMessage({
                clientId,
                number: allMessages.filter((f) => f.fromMe === false)[0]?.from,
                chats: allMessages.map((m) => ({
                    body: m.body,
                    links: m.links,
                    fromMe: m.fromMe,
                    timestamp: m.timestamp,
                })),
                timestamp: allMessages[allMessages.length - 1]?.timestamp,
                replied: allMessages[allMessages.length - 1]?.fromMe,
            });
        } catch (error) {
            console.error("Error fetching all messages:", error);
        }
    });

    client.on("message_create", async (msg) => {
        try {
            const chat = await msg.getChat(); // dapat chat yang terkait dengan pesan ini
            const allMessages = await chat.fetchMessages({ limit: 100 }); // ambil sampai 100 pesan terakhir

            // Kirim ke backend misal
            logMessage({
                clientId,
                number: allMessages.filter((f) => f.fromMe === true)[0]?.to,
                chats: allMessages.map((m) => ({
                    body: m.body,
                    links: m.links,
                    fromMe: m.fromMe,
                    timestamp: m.timestamp,
                })),
                timestamp: allMessages[allMessages.length - 1]?.timestamp,
                replied: allMessages[allMessages.length - 1]?.fromMe,
            });
        } catch (error) {
            console.error("Error fetching all messages:", error);
        }
    });

    client.initialize();
}

// ⏯ Start client (called from Laravel)
app.post("/device/start", (req, res) => {
    const { client_id } = req.body;
    if (!client_id) {
        return res.status(400).json({ message: "client_id is required" });
    }

    if (!clients[client_id]) {
        initClient(client_id);
        return res.json({ message: `Client ${client_id} initialized.` });
    } else {
        return res.json({ message: `Client ${client_id} already running.` });
    }
});

// 📦 Send message
app.post("/send-message", (req, res) => {
    const { client_id, number, message } = req.body;

    if (!clients[client_id] || !clients[client_id].ready) {
        return res
            .status(400)
            .json({ message: "Client not ready or not found" });
    }

    clients[client_id].client
        .sendMessage(number + "@c.us", message)
        .then((response) => res.json({ success: true, response }))
        .catch((err) =>
            res.status(500).json({ success: false, error: err.message })
        );
});

// 📡 Get QR code
app.get("/device/:clientId/qr", (req, res) => {
    const { clientId } = req.params;

    if (!clients[clientId]) {
        return res.status(404).json({ message: "Client not found" });
    }

    if (clients[clientId].ready) {
        return res.json({ message: "Client already connected" });
    }

    if (!clients[clientId].qr) {
        return res.status(404).json({ message: "QR not available yet" });
    }

    console.log("hitted");

    return res.json({ qr: clients[clientId].qr });
});

app.delete("/device/:clientId/delete", async (req, res) => {
    const { clientId } = req.params;

    if (!clients[clientId]) {
        return res.status(404).json({ message: "Client not found" });
    }

    try {
        await clients[clientId].client.destroy(); // Matikan client
        delete clients[clientId]; // Hapus dari memory

        // Hapus folder session (LocalAuth)
        const fs = require("fs");
        const path = `./.wwebjs_auth/session-${clientId}`;
        if (fs.existsSync(path)) {
            fs.rmSync(path, { recursive: true, force: true });
        }

        return res.json({ message: "Client deleted successfully" });
    } catch (error) {
        console.error(error);
        return res.status(500).json({ message: "Failed to delete client" });
    }
});

app.get("/device/:clientId/contacts", async (req, res) => {
    const { clientId } = req.params;

    if (!clients[clientId] || !clients[clientId].ready) {
        return res
            .status(400)
            .json({ message: "Client not ready or not found" });
    }

    try {
        const contacts = await clients[clientId].client.getContacts();

        const formattedContacts = contacts
            .filter((f) => f.type === "in")
            .map((c) => ({
                name: c.name || c.pushname || "Not Initialized",
                number: c.number || c.id.user,
                is_group: c.isGroup,
            }))
            .filter((c) => /^62\d{7,12}$/.test(c.number)); // 62 + 7-12 digit = max 14 digit total

        res.json(formattedContacts);
    } catch (err) {
        console.error(err);
        res.status(500).json({ message: "Failed to fetch contacts" });
    }
});

async function logMessage({
    clientId,
    number,
    fromMe,
    chats,
    timestamp,
    replied,
}) {
    try {
        const cleanedNumber = number.replace(/@c\.us$/, ""); // hapus @c.us di akhir

        const res = await axios.post("http://127.0.0.1:8000/api/message-log", {
            client_id: clientId,
            number: cleanedNumber,
            chats,
            timestamp,
            replied,
        });

        // console.log(res.data);
    } catch (e) {
        console.error("Failed to log message", e.message);
    }
}

async function InitAllMessages(client, clientId) {
    try {
        const chats = await client.getChats();

        for (const chat of chats) {
            const allMessages = await chat.fetchMessages({ limit: 1000 });

            const messages = allMessages.map((m) => ({
                body: m.body,
                links: m.links,
                fromMe: m.fromMe,
                timestamp: m.timestamp,
            }));

            const number =
                allMessages.find((m) => m.fromMe)?.to || chat.id._serialized;

            await logMessage({
                clientId,
                number,
                chats: messages,
                timestamp: allMessages[allMessages.length - 1]?.timestamp,
                replied: allMessages[allMessages.length - 1]?.fromMe,
            });
        }
        console.log(`Berhasil inisialisasi chat`);
    } catch (error) {
        console.error("Gagal inisialisasi semua pesan:", error.message);
    }
}

// 🌐 Server listen
app.listen(port, () => {
    console.log(`WhatsApp bot listening at http://localhost:${port}`);
});
