#!/bin/bash
# ─────────────────────────────────────────────────────────────────
# FishPro Install Script
# ─────────────────────────────────────────────────────────────────
set -e
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}🐟 FishPro — Fish Processing Management System${NC}"
echo -e "${BLUE}══════════════════════════════════════════════${NC}\n"

# 1. Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP tidak ditemukan. Install PHP 8.2+ terlebih dahulu.${NC}"; exit 1
fi
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "${GREEN}✅ PHP ${PHP_VER} ditemukan${NC}"

# 2. Check Composer
if ! command -v composer &> /dev/null; then
    echo -e "${RED}❌ Composer tidak ditemukan. Download dari https://getcomposer.org${NC}"; exit 1
fi
echo -e "${GREEN}✅ Composer ditemukan${NC}"

# 3. Install dependencies
echo -e "\n${YELLOW}📦 Menginstall dependencies...${NC}"
composer install --no-dev --optimize-autoloader

# 4. Setup .env
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}✅ File .env dibuat dari .env.example${NC}"
fi

# 5. Generate app key
php artisan key:generate
echo -e "${GREEN}✅ App key di-generate${NC}"

# 6. Tanya jenis database
echo -e "\n${YELLOW}🗄️  Pilih database:${NC}"
echo "   1) MySQL/MariaDB"
echo "   2) SQLite (development)"
read -p "   Pilihan (1/2): " DB_CHOICE

if [ "$DB_CHOICE" = "2" ]; then
    touch database/database.sqlite
    sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
    sed -i 's/^DB_HOST=/#DB_HOST=/' .env
    sed -i 's/^DB_PORT=/#DB_PORT=/' .env
    sed -i 's/^DB_DATABASE=/#DB_DATABASE=/' .env
    sed -i 's/^DB_USERNAME=/#DB_USERNAME=/' .env
    sed -i 's/^DB_PASSWORD=/#DB_PASSWORD=/' .env
    echo -e "${GREEN}✅ SQLite dikonfigurasi${NC}"
else
    read -p "   DB Host (default: 127.0.0.1): " DB_HOST
    read -p "   DB Name (default: fishpro): " DB_NAME
    read -p "   DB Username (default: root): " DB_USER
    read -s -p "   DB Password: " DB_PASS; echo
    DB_HOST=${DB_HOST:-127.0.0.1}
    DB_NAME=${DB_NAME:-fishpro}
    DB_USER=${DB_USER:-root}
    sed -i "s/DB_HOST=127.0.0.1/DB_HOST=${DB_HOST}/" .env
    sed -i "s/DB_DATABASE=fishpro/DB_DATABASE=${DB_NAME}/" .env
    sed -i "s/DB_USERNAME=root/DB_USERNAME=${DB_USER}/" .env
    sed -i "s/DB_PASSWORD=/DB_PASSWORD=${DB_PASS}/" .env
    echo -e "${GREEN}✅ MySQL dikonfigurasi${NC}"
fi

# 7. Migrasi
echo -e "\n${YELLOW}🔄 Menjalankan migrasi...${NC}"
php artisan migrate

# 8. Seed
read -p "   Muat data demo? (y/n): " SEED_CHOICE
if [ "$SEED_CHOICE" = "y" ] || [ "$SEED_CHOICE" = "Y" ]; then
    php artisan db:seed
    echo -e "${GREEN}✅ Data demo dimuat${NC}"
fi

# 9. Storage link
php artisan storage:link 2>/dev/null || true

# 10. Done!
echo -e "\n${GREEN}══════════════════════════════════════════════${NC}"
echo -e "${GREEN}🎉 Instalasi selesai!${NC}"
echo -e "${GREEN}══════════════════════════════════════════════${NC}"
echo -e "\nJalankan server:"
echo -e "  ${BLUE}php artisan serve${NC}"
echo -e "\nBuka browser: ${BLUE}http://localhost:8000${NC}"
echo -e "\nLogin demo:"
echo -e "  Email   : ${YELLOW}admin@fishpro.com${NC}"
echo -e "  Password: ${YELLOW}password${NC}\n"
