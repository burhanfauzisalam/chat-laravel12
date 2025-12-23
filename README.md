## Materio Chat App (Laravel + MQTT)

Aplikasi ini adalah contoh implementasi **real‑time group chat** berbasis Laravel dengan tampilan **Materio Bootstrap HTML** dan backend realtime menggunakan **MQTT**.

Fitur utama:

- Multi room chat (join/leave room, tambah room sendiri).
- Unread badge per room & divider **“New messages”** pada pesan baru.
- Pencarian room berdasarkan nama/topik (live search).
- Invite link menggunakan **kode unik** (bukan ID).
- Pengiriman pesan teks dan **lampiran file** (gambar / dokumen).
- Upload dan penggunaan **foto profil** untuk setiap user.
- List member di **Room Info** + auto hapus room jika tidak ada member.
- Lazy loading riwayat chat (tombol **Load earlier messages**).

Aplikasi dibangun di atas **Laravel** standar, sehingga seluruh dokumentasi dasar Laravel (routing, migration, dsb.) tetap berlaku.

---

## 1. Persyaratan

- PHP 8.1+
- Composer
- MySQL atau MariaDB
- Node.js (opsional, hanya jika ingin meng-compile asset sendiri)
- Broker MQTT dengan dukungan WebSocket (contoh: Mosquitto yang di-enable WS‑nya)

---

## 2. Instalasi

1. **Clone / copy project**

   ```bash
   git clone <repo-anda>
   cd chat-app
   ```

2. **Install dependency PHP**

   ```bash
   composer install
   ```

3. **Siapkan file `.env`**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Atur koneksi database** di `.env`

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=chat_app
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Atur konfigurasi MQTT** di `.env`

   ```env
   MQTT_HOST=127.0.0.1
   MQTT_WS_PORT=9001        # Port WebSocket broker
   MQTT_USE_TLS=false
   MQTT_CLIENT_ID_PREFIX=laravel-chat-
   ```

6. **Jalankan migrasi database**

   ```bash
   php artisan migrate
   ```

7. **Buat symbolic link storage (jika belum)**

   ```bash
   php artisan storage:link
   ```

8. **Jalankan server Laravel**

   ```bash
   php artisan serve
   ```

   Aplikasi default akan berjalan di `http://127.0.0.1:8000`.

---

## 3. Fitur & Cara Penggunaan

### 3.1. Autentikasi & Profil

- Login menggunakan sistem autentikasi Laravel (contoh: Jetstream/Breeze, sesuaikan dengan implementasi Anda).
- Klik avatar di sidebar kiri untuk membuka **profil user**.
- Di panel profil ini tersedia:
  - Form **upload foto profil** (disimpan di `storage/app/public/avatars`).
  - Tombol **Logout**.

Foto profil akan otomatis digunakan:

- Di avatar sidebar.
- Pada bubble pesan yang dikirim user.
- Pada bubble pesan user lain (dari mapping avatar yang dikirim backend).

### 3.2. Rooms

- Sidebar kiri menampilkan list **Rooms**.
- Di sebelah judul **Rooms** ada tombol **“+”** untuk membuka modal **Add Room**.
- Form tambah room berisi:
  - `Room name`
  - `Topic` (unik, tidak boleh sama dengan room lain).
- Setelah room dibuat:
  - User otomatis join ke room tersebut.
  - Room akan muncul di list, beserta jumlah member.

**Room kosong** (tidak ada user di dalamnya) akan otomatis **dihapus** ketika user terakhir melakukan **Leave room**.

### 3.3. Join via Invite Link

- Di **Room Info** (sidebar kanan) terdapat tombol **Copy link**.
- Link tersebut berisi **kode unik** room, misalnya:

  ```text
  https://your-app.test/rooms/join/{invite_code}
  ```

- Bagikan link ini ke user lain. Ketika dibuka, user akan otomatis diarahkan untuk **join** ke room terkait (jika belum menjadi member).

### 3.4. Room Info & Leave Room

- Klik ikon **info** di header chat untuk membuka **Room Info**.
- Di Room Info terdapat:
  - Nama room & topic.
  - Jumlah member + daftar member (username).
  - Tombol **Copy link** (invite).
  - Tombol **Leave room**.
- Saat menekan **Leave room**, akan muncul konfirmasi SweetAlert.

Jika setelah leave tidak ada user lain di room tersebut, room akan **dihapus** beserta pesan‑pesannya.

### 3.5. Pencarian Room

- Kolom search di bagian atas sidebar Rooms dapat mencari berdasarkan:
  - Nama room.
  - Topic room.
- Hasil filter muncul **real‑time** di list rooms (tanpa reload).

### 3.6. Pengiriman Pesan & File

- Form input di bagian bawah area chat:
  - Ketik pesan di field **“Type your message here…”**.
  - Klik ikon **attachment** untuk memilih file (gambar / dokumen).
  - Setelah file dipilih, nama file akan muncul di badge kecil di samping ikon.
  - Tekan **Send** untuk mengirim.
- Pesan akan:
  - Disimpan ke database via endpoint `messages.store`.
  - Dipublish ke broker MQTT dalam format JSON.
  - Ditampilkan di UI untuk pengirim dan semua user yang subscribe pada room tersebut.
- Lampiran:
  - Jika bertipe gambar, akan ditampilkan preview gambar.
  - Jika file biasa (PDF, DOCX, dll), muncul sebagai bubble dengan ikon paperclip + nama file, dan dapat diklik (link ke storage publik).

### 3.7. Unread Badge & Divider “New Messages”

- Setiap room menyimpan `last_read_at` per user (pivot `room_user`).
- Ketika ada pesan baru di sebuah room dan user **tidak sedang berada** di room tersebut:
  - Badge **unread** akan muncul di item room (jumlah pesan baru).
- Saat user membuka room:
  - `last_read_at` diupdate.
  - Pesan‑pesan yang *baru* (setelah `last_read_at` sebelumnya) akan diberi pembatas **“New messages”** di dalam riwayat chat.
  - Divider ini secara otomatis menghilang setelah pesan dianggap terbaca (di-refresh / masuk kembali).

### 3.8. Lazy Loading Riwayat Chat

- Saat membuka room, hanya **50 pesan terakhir** yang di-load.
- Jika masih ada riwayat lebih lama, di bagian atas list pesan akan muncul tombol **“Load earlier messages”**.
- Klik tombol ini untuk memuat 50 pesan sebelumnya lagi, tanpa mengubah posisi scroll secara mendadak.

---

## 4. Struktur File Penting

- `resources/views/chat.blade.php`  
  Tampilan utama aplikasi chat (layout Materio, sidebar rooms, room info, form kirim pesan, dan script JS MQTT).

- `app/Http/Controllers/ChatController.php`  
  Mengambil data rooms, pesan awal, unread count, avatar user, dan flag riwayat (hasMoreHistory, oldestMessageId).

- `app/Http/Controllers/MessageController.php`  
  Menyimpan pesan baru (teks + file), mengembalikan response JSON yang dipakai untuk publish MQTT dan render bubble.

- `app/Http/Controllers/HistoryMessageController.php`  
  Endpoint AJAX untuk **Load earlier messages**.

- `app/Http/Controllers/RoomController.php`  
  CRUD room (store, join, leave), generate invite code, dan hapus room ketika tidak ada member.

- `app/Http/Controllers/ProfileController.php`  
  Upload dan update foto profil user.

- `app/Models/Message.php`, `app/Models/User.php`  
  Accessor `attachment_url` dan `avatar_url` untuk dipakai di frontend.

- `public/assets/js/app-chat.js`  
  Script template Materio yang mengatur search, sidebar, dan sebagian behavior chat bawaan (submit default sudah dinonaktifkan dengan atribut `data-disable-default-send`).

---

## 5. Catatan Pengembangan

- Jangan mengubah atribut `data-disable-default-send="1"` pada form kirim pesan di `chat.blade.php`, karena akan menyebabkan bubble pesan ganda pada pengirim.
- Jika mengubah struktur payload MQTT, pastikan penyesuaian juga dilakukan di:
  - `MessageController` (response JSON).
  - Script MQTT di `chat.blade.php` (handler `client.on('message')`).
- Untuk mengubah batas jumlah pesan per halaman riwayat, sesuaikan nilai limit (50) di `ChatController` dan `HistoryMessageController`.

---

## 6. Lisensi

Project ini dibangun di atas **Laravel** dan **Materio Bootstrap HTML Admin Template**.  
Lisensi Laravel mengikuti [MIT License](https://opensource.org/licenses/MIT);  
lisensi Materio mengikuti ketentuan dari penyedia templatenya. Silakan sesuaikan penggunaan sesuai lisensi masing‑masing. 
