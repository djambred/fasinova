# Firewall container karya (Docker rootless `runner`)

Membatasi koneksi KELUAR container karya, dicocokkan lewat uid `runner` (proses rootlesskit membuat koneksi di jaringan host).
Diblokir: alamat host sendiri kecuali 80/443 (SSH dll.), jaringan privat (10/8, 172.16/12, 192.168/16, 100.64/10), metadata cloud
(169.254/16), dan IPv6 lokal. Tidak terpengaruh: internet publik, DNS, jaringan antar-container di daemon rootless (mis. `fz-db`).

Pasang (sekali, di server):

    sudo install -m 755 fasinova-runner-firewall /usr/local/sbin/
    echo 'EXTRA_HOST_IPS="<IP publik server>"' | sudo tee /etc/default/fasinova-runner-firewall
    sudo install -m 644 fasinova-runner-firewall.service /etc/systemd/system/
    sudo systemctl daemon-reload && sudo systemctl enable --now fasinova-runner-firewall

Batalkan: `sudo systemctl disable --now fasinova-runner-firewall` (menghapus aturan). Cek: `sudo fasinova-runner-firewall status`.

Catatan uji: subnet jaringan Docker rootless (172.17/16, 172.18/16, ...) sama dengan Docker utama. Uji isolasi harus memakai
container di subnet yang tidak bentrok (mis. `docker network create --subnet 172.30.0.0/24`), kalau tidak, alamat 172.18.x
terjawab oleh jaringan rootless sendiri dan memberi hasil "terbuka" yang menyesatkan.
