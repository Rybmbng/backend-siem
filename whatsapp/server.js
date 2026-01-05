const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcodeTerminal = require('qrcode-terminal'); // Buat di Terminal
const qrcodeImage = require('qrcode'); // Buat jadi file PNG (Dashboard)
const axios = require('axios');
const fs = require('fs');
const path = require('path');
const express = require('express');
const cors = require('cors');

const app = express();
app.use(express.json()); // Pengganti body-parser (lebih modern)
app.use(cors());

// Path gambar untuk Dashboard Laravel
const qrPath = path.join(__dirname, '../public/storage/wa_qr.png');

// Pastikan folder ada biar gak error
const qrDir = path.dirname(qrPath);
if (!fs.existsSync(qrDir)) fs.mkdirSync(qrDir, { recursive: true });

const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: { 
        args: ['--no-sandbox', '--disable-setuid-sandbox'], 
        headless: true 
    }
});

// --- API UNTUK LARAVEL (PORT 3111) ---
app.post('/send-broadcast', async (req, res) => {
    const { to, message } = req.body;
    console.log(`📩 Request kirim ke ${to}`);

    if (!client.info) {
        return res.status(503).json({ status: 'error', message: 'WA Belum Siap/Scan!' });
    }

    try {
        const chatId = to.includes('@c.us') ? to : `${to}@c.us`;
        await client.sendMessage(chatId, message);
        console.log(`✅ Sukses kirim ke ${to}`);
        res.json({ status: 'success', message: 'Terkirim, Bang!' });
    } catch (error) {
        console.error('❌ Gagal kirim:', error.message);
        res.status(500).json({ status: 'error', error: error.message });
    }
});

const PORT = 3111;
app.listen(PORT, () => console.log(`🚀 API Gateway jalan di port ${PORT}`));
// -------------------------

client.on('qr', async (qr) => {
    // 1. Tampilkan di Terminal (Keren & Cepat)
    qrcodeTerminal.generate(qr, { small: true });
    
    // 2. Simpan sebagai PNG (Untuk Dashboard Laravel)
    try {
        await qrcodeImage.toFile(qrPath, qr, { width: 300 });
        console.log('📸 QR Image updated di: ' + qrPath);
    } catch (err) {
        console.error('Gagal simpan gambar QR:', err);
    }
});

client.on('ready', () => {
    console.log('✅ WhatsApp Konek, Bang!');
    // Hapus QR lama biar bersih
    if (fs.existsSync(qrPath)) fs.unlinkSync(qrPath);
});

client.on('disconnected', (reason) => {
    console.log('⚠️ WA Disconnected:', reason);
});

// Webhook untuk Balas Pesan (Opsional)
client.on('message', async (msg) => {
    try {
        // Filter pesan status/broadcast biar gak spam log
        if (msg.from === 'status@broadcast') return;

        const response = await axios.post('http://127.0.0.1:8222/api/wa-webhook', {
            sender: msg.from.split('@')[0],
            message: msg.body
        });
        if (response.data.reply) msg.reply(response.data.reply);
    } catch (error) {
    }
});

client.initialize();