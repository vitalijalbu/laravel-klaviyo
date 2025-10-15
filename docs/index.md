# Klaviyo Event Service Documentation

Un microservizio Laravel enterprise-ready per l'integrazione con Klaviyo attraverso eventi e-commerce.

## 📚 Indice della Documentazione

### 🚀 [Guida Rapida](#guida-rapida)
- Setup iniziale in 5 minuti
- Configurazione base
- Test del servizio

### 🏗️ [Architettura del Sistema](architecture.md)
- Panoramica dell'architettura
- Componenti principali
- Pattern utilizzati
- Diagrammi del flusso dati

### ⚡ [Laravel Service Guide](laravel-service.md)
- Come funziona il servizio Laravel
- DTO, Actions e Request classes
- Job Queue e Worker
- API Endpoints dettagliati

### 🛠️ [Integrazione Sylius](sylius.md)
- Come integrare con Sylius
- Eventi supportati
- Webhook dispatcher generico
- Configurazione YAML

---

## 🚀 Guida Rapida

### Setup Iniziale

```bash
# 1. Clone del repository
git clone <repository-url>
cd klaviyo-service

# 2. Installazione dipendenze
composer install

# 3. Configurazione ambiente
cp .env.example .env
php artisan key:generate

# 4. Configurazione chiavi API
# Edita .env e configura:
# KLAVIYO_API_KEY=pk_your_private_api_key
# SERVICE_API_KEY=your_super_secure_random_key
```

### Avvio con Docker (Raccomandato)

```bash
# Avvia tutti i servizi
docker-compose up -d

# Verifica che tutto funzioni
curl http://localhost:8080/api/health
```

### Avvio Senza Docker

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:work --queue=klaviyo

# Terminal 3: Log monitoring (opzionale)
php artisan pail
```

### Test Rapido

```bash
# Health check
curl http://localhost:8080/api/health

# Test event tracking
curl -X POST http://localhost:8080/api/events/product-view \
  -H "X-API-Key: your_service_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": "123",
    "product_name": "Test Product",
    "price": 29.99,
    "currency": "EUR"
  }'
```

---

## 🔧 Configurazione

### Variabili d'Ambiente Principali

```env
# === APPLICAZIONE ===
APP_NAME="Klaviyo Event Service"
APP_ENV=production
APP_DEBUG=false

# === AUTENTICAZIONE ===
SERVICE_API_KEY=your_super_secure_random_key

# === KLAVIYO ===
KLAVIYO_API_KEY=pk_your_private_api_key
KLAVIYO_API_URL=https://a.klaviyo.com/api

# === QUEUE ===
QUEUE_CONNECTION=redis
KLAVIYO_QUEUE_NAME=klaviyo
KLAVIYO_QUEUE_CONNECTION=redis

# === REDIS ===
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
```

---

## 📋 API Endpoints

### Health Check
```http
GET /api/health
```

### Event Tracking
```http
POST /api/events/track
POST /api/events/product-view
POST /api/events/order-placed
```

### Catalog Management
```http
POST /api/catalog/sync
POST /api/catalog/sync-single
```

Tutti gli endpoint richiedono l'header `X-API-Key` per l'autenticazione.

---

## 🧪 Testing

```bash
# Esegui tutti i test
php artisan test

# Test specifici
php artisan test --filter=EventTrackingTest

# Test con coverage
php artisan test --coverage
```

---

## 📊 Monitoring e Logs

### Log Viewer
```bash
# Visualizza logs in tempo reale
php artisan pail

# Accesso web ai logs (se installato)
http://localhost:8080/log-viewer
```

### Queue Monitoring
```bash
# Stato delle code
php artisan queue:work --queue=klaviyo --verbose

# Statistiche
php artisan horizon:status
```

---

## 🚀 Deploy in Produzione

### Con Docker
```bash
# Build immagini
docker-compose build

# Deploy
docker-compose -f docker-compose.prod.yml up -d
```

### Setup Database
```bash
# Migrazione database (se necessario)
php artisan migrate --force

# Cache ottimizzazione
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🛡️ Sicurezza

### API Authentication
- Tutte le API richiedono header `X-API-Key`
- Chiavi API configurabili via environment
- Rate limiting automatico

### Data Validation
- Form Request classes per ogni endpoint
- Validazione tipizzata e sanitizzazione
- Messaggi di errore personalizzati

### GDPR Compliance
- Endpoint per cancellazione profili
- Gestione consensi
- Data retention policies

---

## 🔗 Link Utili

- **[Architettura Dettagliata](architecture.md)** - Schema completo del sistema
- **[Laravel Service Guide](laravel-service.md)** - Implementazione Laravel
- **[Integrazione Sylius](sylius.md)** - Setup lato e-commerce
- **[API Reference](api-reference.md)** - Documentazione completa API
- **[Troubleshooting](troubleshooting.md)** - Risoluzione problemi comuni

---

## 🤝 Contribuire

1. Fork del repository
2. Crea feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Apri Pull Request

---

## 📄 License

Distribuito sotto licenza MIT. Vedi `LICENSE` per maggiori informazioni.

---

## 📞 Supporto

- **Issues**: [GitHub Issues](https://github.com/your-repo/issues)
- **Documentazione**: [docs/](docs/)
- **Email**: support@yourcompany.com