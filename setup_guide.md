# 🚀 ScholarDesk Project: Complete Setup Guide (For Collaborators)

এই প্রজেক্টে কাজ শুরু করার জন্য তোমার উইন্ডোজ পিসিতে প্রো-লেভেলের লিনাক্স এনভায়রনমেন্ট (WSL) সেটআপ করা থেকে শুরু করে প্রজেক্ট রান করা পর্যন্ত সব ধাপ নিচে দেওয়া হলো।

---

## 🛠️ পর্ব ১: সফটওয়্যার এবং এনভায়রনমেন্ট সেটআপ

### ১. WSL এবং Ubuntu ইন্সটল করা (উইন্ডোজে)
১. উইন্ডোজের Start Menu-তে গিয়ে **PowerShell** সার্চ করে **"Run as Administrator"** হিসেবে ওপেন করো।
২. নিচের কমান্ডটি লিখে Enter চাপো:
   ```bash
   wsl --install

```

৩. ইন্সটল শেষ হলে পিসি একবার **Restart** দাও।
৪. পিসি ওপেন হলে Ubuntu-এর একটি টার্মিনাল আসবে। সেখানে নিজের পছন্দমতো একটি **Username** এবং **Password** সেট করে নাও *(পাসওয়ার্ড টাইপ করার সময় স্ক্রিনে দেখা যাবে না, জাস্ট টাইপ করে Enter দিবে)*।

### ২. VS Code সেটআপ

১. পিসিতে **VS Code** ইন্সটল করা না থাকলে করে নাও।
২. VS Code ওপেন করে Extensions থেকে **"WSL"** (Microsoft-এর তৈরি) এক্সটেনশনটি ইন্সটল করে নাও।

### ৩. লিনাক্সে প্রয়োজনীয় সফটওয়্যার ইন্সটল করা

তোমার পিসির Start Menu থেকে **Ubuntu** অ্যাপটি ওপেন করো এবং নিচের কমান্ডগুলো সিরিয়ালি রান করো *(পাসওয়ার্ড চাইলে তোমার লিনাক্স পাসওয়ার্ড দিবে)*:

**সিস্টেম আপডেট:**

```bash
sudo apt update && sudo apt upgrade -y

```

**PHP এবং লারাভেলের এক্সটেনশন ইন্সটল:**

```bash
sudo apt install php php-cli php-pgsql php-zip php-mbstring php-curl php-xml php-bcmath -y

```

**Composer ইন্সটল:**

```bash
sudo apt install composer -y

```

**Node.js (v22) ইন্সটল (ফ্রন্টএন্ডের জন্য):**

```bash
curl -fsSL [https://deb.nodesource.com/setup_22.x](https://deb.nodesource.com/setup_22.x) | sudo -E bash -
sudo apt-get install -y nodejs

```

**PostgreSQL (ডেটাবেজ সার্ভার) ইন্সটল ও চালু করা:**

```bash
sudo apt install postgresql postgresql-contrib -y
sudo service postgresql start

```

**ডেটাবেজের পাসওয়ার্ড সেট করা:**

```bash
sudo -u postgres psql

```

*এবার `\password postgres` লিখে একটি সহজ পাসওয়ার্ড (যেমন: `1234` বা `root`) দাও। এরপর `\q` লিখে বের হয়ে আসো।*

**Git কনফিগার করা:**

```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

```

### ৪. DBeaver ইন্সটল (উইন্ডোজে)

১. ব্রাউজার থেকে **DBeaver Community Edition** ডাউনলোড করে উইন্ডোজে ইন্সটল করো।
২. DBeaver ওপেন করে "New Database Connection" থেকে **PostgreSQL** সিলেক্ট করো।
৩. Username: `postgres` এবং Password: (টার্মিনালে যেটা দিয়েছো) দিয়ে কানেক্ট করো।
৪. **গুরুত্বপূর্ণ সেটিং:** কানেকশনের ওপর রাইট-ক্লিক করে **Edit Connection** -> **PostgreSQL** মেনু থেকে **"Show all databases"** অপশনে টিক (✔) দিয়ে OK করে দাও।

---

## ⚙️ পর্ব ২: প্রজেক্ট ক্লোন ও কনফিগারেশন

Ubuntu টার্মিনালে নিচের কমান্ডগুলো সিরিয়ালি দাও:

### ১. গিটহাব থেকে প্রজেক্ট নামানো

```bash
git clone https://github.com/bodruzzaman-bkm/scholardesk.git

```

*(নোট: `yourusername`-এর জায়গায় আসল লিংকটি বসিয়ে নিবে)*

### ২. প্রজেক্ট ফোল্ডারে ঢোকা এবং VS Code-এ ওপেন করা

```bash
cd scholardesk
code .

```

### ৩. প্যাকেজ ইন্সটলেশন

VS Code-এর টার্মিনাল (Ubuntu WSL) ওপেন করে নিচের কমান্ড দুটি দাও:

```bash
composer install
npm install

```

### ৪. এনভায়রনমেন্ট (.env) ফাইল তৈরি ও কী-জেনারেট

```bash
cp .env.example .env
php artisan key:generate

```

### ৫. ডেটাবেজ সেটআপ

১. DBeaver-এ গিয়ে `scholardesk` নামে একটি নতুন ডেটাবেজ (`Create -> Database`) তৈরি করো।
২. VS Code-এ `.env` ফাইলটি ওপেন করে ডেটাবেজের অংশটুকু এভাবে এডিট করো:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=scholardesk
DB_USERNAME=postgres
DB_PASSWORD=your database password

```

৩. এরপর টার্মিনালে টেবিলগুলো তৈরি করার জন্য কমান্ড দাও:

```bash
php artisan migrate

```

---

## ▶️ পর্ব ৩: প্রজেক্ট রান করা

সব সেটআপ শেষ! প্রজেক্ট ব্রাউজারে দেখার জন্য VS Code-এ দুটি টার্মিনাল ট্যাব ওপেন করে রাখতে হবে:

**টার্মিনাল ১ (ডিজাইন কম্পাইল করার জন্য):**

```bash
npm run dev

```

**টার্মিনাল ২ (ব্যাকএন্ড সার্ভার চালানোর জন্য):**

```bash
php artisan serve

```

এবার ব্রাউজারে গিয়ে `http://localhost:8000` লিংকে গেলেই প্রজেক্ট রেডি! 🎉

> **💡 প্রো টিপ:** পরবর্তীতে প্রতিদিন কাজ শুরু করার আগে টার্মিনালে `git pull` কমান্ড দিয়ে আপডেট নামিয়ে নেবে, যাতে সবার কোড সিঙ্ক থাকে।

```

```