# Sylius Integration Guide

Guida completa per integrare Sylius con il servizio Laravel Klaviyo. Questo documento fornisce esempi pratici, configurazioni e best practices per implementare il tracking degli eventi e-commerce da Sylius.

## 🛍️ Overview Integrazione

L'integrazione Sylius-Klaviyo permette di tracciare automaticamente tutti gli eventi e-commerce importanti e sincronizzare i dati del catalogo prodotti in modo seamless.

### Eventi Supportati

- **Product Views** - Visualizzazione prodotti
- **Add to Cart** - Aggiunta al carrello  
- **Remove from Cart** - Rimozione dal carrello
- **Started Checkout** - Inizio checkout
- **Placed Order** - Ordine completato
- **Refunded Order** - Ordine rimborsato
- **Catalog Sync** - Sincronizzazione catalogo

---

## 🏗️ Setup Iniziale

### 1. Configurazione Client HTTP

Creare un service per comunicare con il microservizio Laravel:

```php
<?php
// src/Service/KlaviyoService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class KlaviyoService
{
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $parameterBag;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $parameterBag,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->parameterBag = $parameterBag;
        $this->logger = $logger;
    }

    private function getApiUrl(): string
    {
        return $this->parameterBag->get('klaviyo.api_url');
    }

    private function getApiKey(): string
    {
        return $this->parameterBag->get('klaviyo.api_key');
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->getApiKey(),
        ];
    }

    public function trackEvent(array $eventData): bool
    {
        try {
            $response = $this->httpClient->request('POST', 
                $this->getApiUrl() . '/api/events/track',
                [
                    'headers' => $this->getHeaders(),
                    'json' => $eventData,
                    'timeout' => 10,
                ]
            );

            if ($response->getStatusCode() === 200) {
                $this->logger->info('Klaviyo event tracked successfully', [
                    'event' => $eventData['event'] ?? 'unknown'
                ]);
                return true;
            }

            $this->logger->error('Klaviyo API error', [
                'status' => $response->getStatusCode(),
                'response' => $response->getContent(false)
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Klaviyo request failed', [
                'error' => $e->getMessage(),
                'event' => $eventData['event'] ?? 'unknown'
            ]);
            return false;
        }
    }
}
```

### 2. Configurazione Parameters

```yaml
# config/packages/klaviyo.yaml
parameters:
    klaviyo.api_url: '%env(KLAVIYO_SERVICE_URL)%'
    klaviyo.api_key: '%env(KLAVIYO_SERVICE_API_KEY)%'
    klaviyo.enabled: '%env(bool:KLAVIYO_ENABLED)%'
```

### 3. Environment Variables

```env
# .env
KLAVIYO_SERVICE_URL=http://localhost:8080
KLAVIYO_SERVICE_API_KEY=your_super_secure_api_key
KLAVIYO_ENABLED=true
```

### 4. Service Registration

```yaml
# config/services.yaml
services:
    App\Service\KlaviyoService:
        arguments:
            $httpClient: '@http_client'
            $parameterBag: '@parameter_bag'
            $logger: '@monolog.logger.klaviyo'

    # Logger dedicato per Klaviyo
    monolog.logger.klaviyo:
        class: Psr\Log\LoggerInterface
        factory: ['@monolog.logger_factory', 'create']
        arguments: ['klaviyo']
```

---

## 📦 Event Subscribers

### ProductViewSubscriber

Traccia le visualizzazioni dei prodotti:

```php
<?php
// src/EventSubscriber/KlaviyoProductViewSubscriber.php

namespace App\EventSubscriber;

use App\Service\KlaviyoService;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

class KlaviyoProductViewSubscriber implements EventSubscriberInterface
{
    private KlaviyoService $klaviyoService;
    private Security $security;

    public function __construct(
        KlaviyoService $klaviyoService,
        Security $security
    ) {
        $this->klaviyoService = $klaviyoService;
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(PostResponseEvent $event): void
    {
        $request = $event->getRequest();
        
        // Verifica se è una pagina prodotto
        if (!$this->isProductPage($request)) {
            return;
        }

        $product = $request->attributes->get('product');
        if (!$product instanceof ProductInterface) {
            return;
        }

        $this->trackProductView($product);
    }

    private function isProductPage(Request $request): bool
    {
        return $request->attributes->get('_route') === 'sylius_shop_product_show';
    }

    private function trackProductView(ProductInterface $product): void
    {
        $eventData = [
            'event' => 'Viewed Product',
            'properties' => [
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
                'product_slug' => $product->getSlug(),
                'price' => $this->getProductPrice($product),
                'currency' => $this->getCurrency(),
                'categories' => $this->getProductCategories($product),
                'sku' => $product->getCode(),
                'in_stock' => $product->isTracked() ? $product->getOnHand() > 0 : true,
                'image_url' => $this->getProductImageUrl($product),
                'product_url' => $this->generateProductUrl($product),
            ],
        ];

        // Aggiungi dati customer se loggato
        $customer = $this->security->getUser();
        if ($customer instanceof CustomerInterface) {
            $eventData['customer'] = $this->buildCustomerData($customer);
        }

        $this->klaviyoService->trackEvent($eventData);
    }

    private function getProductPrice(ProductInterface $product): ?float
    {
        $variant = $product->getVariants()->first();
        if (!$variant) {
            return null;
        }

        $channelPricing = $variant->getChannelPricingForChannel($this->getCurrentChannel());
        return $channelPricing ? $channelPricing->getPrice() / 100 : null;
    }

    private function getProductCategories(ProductInterface $product): array
    {
        return $product->getTaxons()->map(fn($taxon) => $taxon->getName())->toArray();
    }

    private function buildCustomerData(CustomerInterface $customer): array
    {
        return [
            'email' => $customer->getEmail(),
            'first_name' => $customer->getFirstName(),
            'last_name' => $customer->getLastName(),
            'phone_number' => $customer->getPhoneNumber(),
        ];
    }
}
```

### CartSubscriber

Traccia le azioni del carrello:

```php
<?php
// src/EventSubscriber/KlaviyoCartSubscriber.php

namespace App\EventSubscriber;

use App\Service\KlaviyoService;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class KlaviyoCartSubscriber implements EventSubscriberInterface
{
    private KlaviyoService $klaviyoService;

    public function __construct(KlaviyoService $klaviyoService)
    {
        $this->klaviyoService = $klaviyoService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.order_item.post_add' => 'onItemAddedToCart',
            'sylius.order_item.post_remove' => 'onItemRemovedFromCart',
        ];
    }

    public function onItemAddedToCart(ResourceControllerEvent $event): void
    {
        $orderItem = $event->getSubject();
        
        if (!$orderItem instanceof OrderItemInterface) {
            return;
        }

        $order = $orderItem->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->trackAddToCart($orderItem, $order);
    }

    public function onItemRemovedFromCart(ResourceControllerEvent $event): void
    {
        $orderItem = $event->getSubject();
        
        if (!$orderItem instanceof OrderItemInterface) {
            return;
        }

        $order = $orderItem->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->trackRemoveFromCart($orderItem, $order);
    }

    private function trackAddToCart(OrderItemInterface $orderItem, OrderInterface $order): void
    {
        $product = $orderItem->getProduct();
        $variant = $orderItem->getVariant();

        $eventData = [
            'event' => 'Added to Cart',
            'properties' => [
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
                'variant_id' => $variant->getId(),
                'variant_name' => $variant->getName(),
                'sku' => $variant->getCode(),
                'quantity' => $orderItem->getQuantity(),
                'unit_price' => $orderItem->getUnitPrice() / 100,
                'total' => $orderItem->getTotal() / 100,
                'currency' => $order->getCurrencyCode(),
                'cart_total' => $order->getTotal() / 100,
                'cart_items_count' => $order->getItems()->count(),
            ],
        ];

        $customer = $order->getCustomer();
        if ($customer) {
            $eventData['customer'] = [
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
            ];
        }

        $this->klaviyoService->trackEvent($eventData);
    }

    private function trackRemoveFromCart(OrderItemInterface $orderItem, OrderInterface $order): void
    {
        $product = $orderItem->getProduct();
        $variant = $orderItem->getVariant();

        $eventData = [
            'event' => 'Removed from Cart',
            'properties' => [
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
                'variant_id' => $variant->getId(),
                'variant_name' => $variant->getName(),
                'sku' => $variant->getCode(),
                'quantity' => $orderItem->getQuantity(),
                'unit_price' => $orderItem->getUnitPrice() / 100,
                'total' => $orderItem->getTotal() / 100,
                'currency' => $order->getCurrencyCode(),
                'cart_total' => $order->getTotal() / 100,
                'cart_items_count' => $order->getItems()->count(),
            ],
        ];

        $customer = $order->getCustomer();
        if ($customer) {
            $eventData['customer'] = [
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
            ];
        }

        $this->klaviyoService->trackEvent($eventData);
    }
}
```

### CheckoutSubscriber

Traccia l'inizio del checkout e gli ordini completati:

```php
<?php
// src/EventSubscriber/KlaviyoCheckoutSubscriber.php

namespace App\EventSubscriber;

use App\Service\KlaviyoService;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\OrderTransitions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;

class KlaviyoCheckoutSubscriber implements EventSubscriberInterface
{
    private KlaviyoService $klaviyoService;

    public function __construct(KlaviyoService $klaviyoService)
    {
        $this->klaviyoService = $klaviyoService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.sylius_order_checkout.transition.address' => 'onCheckoutStarted',
            'workflow.sylius_order.transition.fulfill' => 'onOrderCompleted',
        ];
    }

    public function onCheckoutStarted(TransitionEvent $event): void
    {
        $order = $event->getSubject();
        
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->trackStartedCheckout($order);
    }

    public function onOrderCompleted(TransitionEvent $event): void
    {
        $order = $event->getSubject();
        
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->trackOrderPlaced($order);
    }

    private function trackStartedCheckout(OrderInterface $order): void
    {
        $eventData = [
            'event' => 'Started Checkout',
            'properties' => [
                'checkout_url' => $this->generateCheckoutUrl($order),
                'cart_total' => $order->getTotal() / 100,
                'currency' => $order->getCurrencyCode(),
                'items_count' => $order->getItems()->count(),
                'items' => $this->buildItemsData($order),
            ],
        ];

        $customer = $order->getCustomer();
        if ($customer) {
            $eventData['customer'] = [
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
            ];
        }

        $this->klaviyoService->trackEvent($eventData);
    }

    private function trackOrderPlaced(OrderInterface $order): void
    {
        $eventData = [
            'event' => 'Placed Order',
            'properties' => [
                'order_id' => $order->getId(),
                'order_number' => $order->getNumber(),
                'total' => $order->getTotal() / 100,
                'currency' => $order->getCurrencyCode(),
                'subtotal' => $order->getItemsTotal() / 100,
                'tax' => $order->getTaxTotal() / 100,
                'shipping' => $order->getShippingTotal() / 100,
                'discount' => abs($order->getOrderPromotionTotal()) / 100,
                'items' => $this->buildItemsData($order),
                'billing_address' => $this->buildAddressData($order->getBillingAddress()),
                'shipping_address' => $this->buildAddressData($order->getShippingAddress()),
                'payment_method' => $this->getPaymentMethodName($order),
                'shipping_method' => $this->getShippingMethodName($order),
            ],
        ];

        $customer = $order->getCustomer();
        if ($customer) {
            $eventData['customer'] = [
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
                'phone_number' => $customer->getPhoneNumber(),
            ];
        }

        $this->klaviyoService->trackEvent($eventData);
    }

    private function buildItemsData(OrderInterface $order): array
    {
        $items = [];

        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $variant = $orderItem->getVariant();

            $items[] = [
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
                'variant_id' => $variant->getId(),
                'variant_name' => $variant->getName(),
                'sku' => $variant->getCode(),
                'quantity' => $orderItem->getQuantity(),
                'unit_price' => $orderItem->getUnitPrice() / 100,
                'total' => $orderItem->getTotal() / 100,
                'categories' => $product->getTaxons()->map(fn($taxon) => $taxon->getName())->toArray(),
            ];
        }

        return $items;
    }

    private function buildAddressData($address): ?array
    {
        if (!$address) {
            return null;
        }

        return [
            'first_name' => $address->getFirstName(),
            'last_name' => $address->getLastName(),
            'company' => $address->getCompany(),
            'address1' => $address->getStreet(),
            'city' => $address->getCity(),
            'postal_code' => $address->getPostcode(),
            'country' => $address->getCountryCode(),
            'province' => $address->getProvinceCode(),
        ];
    }

    private function getPaymentMethodName(OrderInterface $order): ?string
    {
        $lastPayment = $order->getLastPayment();
        return $lastPayment?->getMethod()?->getName();
    }

    private function getShippingMethodName(OrderInterface $order): ?string
    {
        $shipment = $order->getShipments()->first();
        return $shipment?->getMethod()?->getName();
    }
}
```

---

## 🛒 Catalog Sync Command

Comando per sincronizzare il catalogo prodotti:

```php
<?php
// src/Command/KlaviyoSyncCatalogCommand.php

namespace App\Command;

use App\Service\KlaviyoService;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'klaviyo:sync-catalog',
    description: 'Sync product catalog with Klaviyo'
)]
class KlaviyoSyncCatalogCommand extends Command
{
    private KlaviyoService $klaviyoService;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        KlaviyoService $klaviyoService,
        ProductRepositoryInterface $productRepository
    ) {
        $this->klaviyoService = $klaviyoService;
        $this->productRepository = $productRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for sync', 50)
            ->addOption('product-id', 'p', InputOption::VALUE_OPTIONAL, 'Sync specific product ID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be synced without actually syncing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        $productId = $input->getOption('product-id');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be sent to Klaviyo');
        }

        if ($productId) {
            return $this->syncSingleProduct($io, $productId, $dryRun);
        }

        return $this->syncAllProducts($io, $batchSize, $dryRun);
    }

    private function syncSingleProduct(SymfonyStyle $io, string $productId, bool $dryRun): int
    {
        $product = $this->productRepository->find($productId);
        
        if (!$product instanceof ProductInterface) {
            $io->error("Product with ID {$productId} not found");
            return Command::FAILURE;
        }

        $productData = $this->buildProductData($product);
        
        if ($dryRun) {
            $io->success('Would sync product: ' . $product->getName());
            $io->table(['Field', 'Value'], array_map(fn($k, $v) => [$k, is_array($v) ? json_encode($v) : $v], array_keys($productData), $productData));
            return Command::SUCCESS;
        }

        $eventData = [
            'products' => [$productData]
        ];

        $success = $this->klaviyoService->syncCatalog($eventData);
        
        if ($success) {
            $io->success("Product {$product->getName()} synced successfully");
            return Command::SUCCESS;
        } else {
            $io->error("Failed to sync product {$product->getName()}");
            return Command::FAILURE;
        }
    }

    private function syncAllProducts(SymfonyStyle $io, int $batchSize, bool $dryRun): int
    {
        $products = $this->productRepository->findBy(['enabled' => true]);
        $totalProducts = count($products);
        
        $io->title("Syncing {$totalProducts} products with Klaviyo");
        
        $progressBar = $io->createProgressBar($totalProducts);
        $progressBar->start();

        $batches = array_chunk($products, $batchSize);
        $successCount = 0;
        $failureCount = 0;

        foreach ($batches as $batch) {
            $batchData = [];
            
            foreach ($batch as $product) {
                $batchData[] = $this->buildProductData($product);
                $progressBar->advance();
            }

            if ($dryRun) {
                $io->writeln("\nWould sync batch of " . count($batchData) . " products");
                continue;
            }

            $eventData = [
                'products' => $batchData
            ];

            $success = $this->klaviyoService->syncCatalog($eventData);
            
            if ($success) {
                $successCount += count($batchData);
            } else {
                $failureCount += count($batchData);
                $io->writeln("\nFailed to sync batch");
            }

            // Rate limiting - pausa tra batch
            usleep(100000); // 100ms
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($dryRun) {
            $io->success("DRY RUN: Would sync {$totalProducts} products");
        } else {
            $io->success("Sync completed: {$successCount} successful, {$failureCount} failed");
        }

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function buildProductData(ProductInterface $product): array
    {
        $variant = $product->getVariants()->first();
        $channelPricing = $variant?->getChannelPricingForChannel($this->getCurrentChannel());
        
        return [
            'product_id' => $product->getId(),
            'product_name' => $product->getName(),
            'description' => $product->getDescription(),
            'sku' => $product->getCode(),
            'price' => $channelPricing ? $channelPricing->getPrice() / 100 : 0,
            'currency' => $this->getCurrentChannel()->getBaseCurrency()->getCode(),
            'categories' => $product->getTaxons()->map(fn($taxon) => $taxon->getName())->toArray(),
            'product_url' => $this->generateProductUrl($product),
            'image_url' => $this->getProductImageUrl($product),
            'in_stock' => $product->isTracked() ? $product->getOnHand() > 0 : true,
            'attributes' => $this->getProductAttributes($product),
        ];
    }

    private function getProductAttributes(ProductInterface $product): array
    {
        $attributes = [];
        
        foreach ($product->getAttributes() as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            $attributes[$attribute->getCode()] = $attributeValue->getValue();
        }

        return $attributes;
    }
}
```

---

## 🎨 Frontend JavaScript Integration

### Product Page Tracking

```javascript
// assets/js/klaviyo-tracking.js

class KlaviyoTracker {
    constructor() {
        this.apiUrl = window.klaviyoConfig?.apiUrl || '/api/klaviyo';
        this.enabled = window.klaviyoConfig?.enabled || false;
        
        if (this.enabled) {
            this.initializeTracking();
        }
    }

    initializeTracking() {
        // Track page views automaticamente
        this.trackPageView();
        
        // Track add to cart
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-klaviyo-add-to-cart]')) {
                this.trackAddToCartClick(e.target);
            }
        });

        // Track newsletter signup
        document.addEventListener('submit', (e) => {
            if (e.target.matches('[data-klaviyo-newsletter]')) {
                this.trackNewsletterSignup(e.target);
            }
        });
    }

    async trackEvent(eventName, properties = {}, customerData = null) {
        if (!this.enabled) return;

        const payload = {
            event: eventName,
            properties: properties,
            customer: customerData || this.getCustomerData(),
            timestamp: new Date().toISOString()
        };

        try {
            const response = await fetch(`${this.apiUrl}/track`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                console.error('Klaviyo tracking failed:', response.statusText);
            }
        } catch (error) {
            console.error('Klaviyo tracking error:', error);
        }
    }

    trackPageView() {
        // Solo per pagine prodotto
        const productData = this.getProductDataFromPage();
        if (!productData) return;

        this.trackEvent('Viewed Product', {
            product_id: productData.id,
            product_name: productData.name,
            price: productData.price,
            currency: productData.currency,
            product_url: window.location.href,
            categories: productData.categories
        });
    }

    trackAddToCartClick(button) {
        const form = button.closest('form');
        const formData = new FormData(form);
        
        const productData = this.getProductDataFromPage();
        const variantId = formData.get('sylius_add_to_cart[cartItem][variant]');
        const quantity = formData.get('sylius_add_to_cart[cartItem][quantity]') || 1;

        // Track immediately (optimistic)
        this.trackEvent('Added to Cart', {
            product_id: productData.id,
            product_name: productData.name,
            variant_id: variantId,
            quantity: parseInt(quantity),
            unit_price: productData.price,
            currency: productData.currency
        });
    }

    trackNewsletterSignup(form) {
        const emailInput = form.querySelector('input[type="email"]');
        const email = emailInput?.value;

        if (email) {
            this.trackEvent('Subscribed to Newsletter', {
                email: email,
                source: 'website_footer'
            }, {
                email: email
            });
        }
    }

    getProductDataFromPage() {
        // Estrae dati prodotto dal DOM o da variabili globali
        const productMeta = document.querySelector('meta[name="product-data"]');
        if (!productMeta) return null;

        try {
            return JSON.parse(productMeta.content);
        } catch (e) {
            console.error('Failed to parse product data:', e);
            return null;
        }
    }

    getCustomerData() {
        // Estrae dati customer se loggato
        const customerMeta = document.querySelector('meta[name="customer-data"]');
        if (!customerMeta) return null;

        try {
            return JSON.parse(customerMeta.content);
        } catch (e) {
            return null;
        }
    }
}

// Initialize quando DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    new KlaviyoTracker();
});
```

### Template Integration

```twig
{# templates/bundles/SyliusShopBundle/Product/show.html.twig #}

{% block metatags %}
    {{ parent() }}
    
    {# Product data per JavaScript tracking #}
    <meta name="product-data" content="{{ {
        id: product.id,
        name: product.name,
        price: product.variants.first.channelPricingForChannel(sylius.channel).price / 100,
        currency: sylius.channel.baseCurrency.code,
        categories: product.taxons|map(t => t.name)|list,
        sku: product.code
    }|json_encode|escape('html_attr') }}">
    
    {# Customer data se loggato #}
    {% if sylius.customer %}
        <meta name="customer-data" content="{{ {
            email: sylius.customer.email,
            first_name: sylius.customer.firstName,
            last_name: sylius.customer.lastName
        }|json_encode|escape('html_attr') }}">
    {% endif %}
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    
    <script>
        window.klaviyoConfig = {
            enabled: {{ klaviyo_enabled|default(false) ? 'true' : 'false' }},
            apiUrl: '{{ path('klaviyo_api_base') }}'
        };
    </script>
    
    <script src="{{ asset('js/klaviyo-tracking.js') }}"></script>
{% endblock %}
```

---

## 🔧 Advanced Features

### Custom Event Builder

```php
<?php
// src/Builder/KlaviyoEventBuilder.php

namespace App\Builder;

class KlaviyoEventBuilder
{
    private array $eventData = [];

    public function __construct(string $eventName)
    {
        $this->eventData = [
            'event' => $eventName,
            'properties' => [],
            'customer' => null,
            'timestamp' => null,
        ];
    }

    public static function create(string $eventName): self
    {
        return new self($eventName);
    }

    public function withProperty(string $key, $value): self
    {
        $this->eventData['properties'][$key] = $value;
        return $this;
    }

    public function withProperties(array $properties): self
    {
        $this->eventData['properties'] = array_merge(
            $this->eventData['properties'],
            $properties
        );
        return $this;
    }

    public function withCustomer(array $customer): self
    {
        $this->eventData['customer'] = $customer;
        return $this;
    }

    public function withTimestamp(\DateTime $timestamp): self
    {
        $this->eventData['timestamp'] = $timestamp->format('c');
        return $this;
    }

    public function build(): array
    {
        if (!$this->eventData['timestamp']) {
            $this->eventData['timestamp'] = (new \DateTime())->format('c');
        }

        return $this->eventData;
    }
}

// Usage:
$event = KlaviyoEventBuilder::create('Custom Event')
    ->withProperty('custom_field', 'value')
    ->withCustomer(['email' => 'user@example.com'])
    ->build();
```

### Conditional Tracking

```php
<?php
// src/Service/KlaviyoConditionalService.php

namespace App\Service;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

class KlaviyoConditionalService
{
    private KlaviyoService $klaviyoService;
    private array $config;

    public function __construct(
        KlaviyoService $klaviyoService,
        array $klaviyoConfig
    ) {
        $this->klaviyoService = $klaviyoService;
        $this->config = $klaviyoConfig;
    }

    public function shouldTrackEvent(string $eventName, ?CustomerInterface $customer = null): bool
    {
        // Non trackare se disabilitato
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        // Verifica optin customer
        if ($customer && !$this->isCustomerOptedIn($customer)) {
            return false;
        }

        // Verifica blacklist eventi
        $blacklist = $this->config['event_blacklist'] ?? [];
        if (in_array($eventName, $blacklist)) {
            return false;
        }

        // Verifica whitelist se presente
        $whitelist = $this->config['event_whitelist'] ?? null;
        if ($whitelist && !in_array($eventName, $whitelist)) {
            return false;
        }

        return true;
    }

    public function shouldTrackOrder(OrderInterface $order): bool
    {
        // Non trackare ordini test
        if ($this->isTestOrder($order)) {
            return false;
        }

        // Non trackare ordini sotto soglia minima
        $minAmount = $this->config['min_order_amount'] ?? 0;
        if ($order->getTotal() < $minAmount * 100) {
            return false;
        }

        return true;
    }

    private function isCustomerOptedIn(CustomerInterface $customer): bool
    {
        // Implementa logica opt-in basata su preferenze customer
        // Esempio: verifica se customer ha accettato marketing
        return $customer->isSubscribedToNewsletter();
    }

    private function isTestOrder(OrderInterface $order): bool
    {
        // Identifica ordini di test (email di test, importi specifici, etc.)
        $testEmails = $this->config['test_emails'] ?? [];
        $customerEmail = $order->getCustomer()?->getEmail();
        
        return in_array($customerEmail, $testEmails);
    }
}
```

### Batch Processing

```php
<?php
// src/Service/KlaviyoBatchService.php

namespace App\Service;

class KlaviyoBatchService
{
    private KlaviyoService $klaviyoService;
    private array $eventQueue = [];
    private int $batchSize;

    public function __construct(
        KlaviyoService $klaviyoService,
        int $batchSize = 50
    ) {
        $this->klaviyoService = $klaviyoService;
        $this->batchSize = $batchSize;
    }

    public function queueEvent(array $eventData): void
    {
        $this->eventQueue[] = $eventData;

        if (count($this->eventQueue) >= $this->batchSize) {
            $this->flushQueue();
        }
    }

    public function flushQueue(): void
    {
        if (empty($this->eventQueue)) {
            return;
        }

        $batches = array_chunk($this->eventQueue, $this->batchSize);

        foreach ($batches as $batch) {
            $this->klaviyoService->trackEventsBatch($batch);
        }

        $this->eventQueue = [];
    }

    public function __destruct()
    {
        // Assicurati che tutti gli eventi vengano inviati
        $this->flushQueue();
    }
}
```

---

## 📊 Performance Optimization

### Async Processing

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            klaviyo.bus:
                middleware:
                    - validation
                    - doctrine_ping_connection
                    - doctrine_close_connection

        transports:
            klaviyo:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: klaviyo_events
                    exchange:
                        name: klaviyo
                        type: topic

        routing:
            'App\Message\KlaviyoEvent': klaviyo
```

```php
<?php
// src/Message/KlaviyoEvent.php

namespace App\Message;

class KlaviyoEvent
{
    public function __construct(
        private array $eventData,
        private int $retryCount = 0
    ) {}

    public function getEventData(): array
    {
        return $this->eventData;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }
}

// src/MessageHandler/KlaviyoEventHandler.php
namespace App\MessageHandler;

use App\Message\KlaviyoEvent;
use App\Service\KlaviyoService;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class KlaviyoEventHandler implements MessageHandlerInterface
{
    public function __construct(private KlaviyoService $klaviyoService)
    {}

    public function __invoke(KlaviyoEvent $message)
    {
        $this->klaviyoService->trackEvent($message->getEventData());
    }
}
```

### Caching Strategy

```php
<?php
// src/Service/KlaviyoCacheService.php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class KlaviyoCacheService
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function getCachedProductData(int $productId): ?array
    {
        return $this->cache->get(
            "klaviyo.product.{$productId}",
            function (ItemInterface $item) use ($productId) {
                $item->expiresAfter(3600); // 1 hour
                
                // Return null per cache miss, 
                // sarà popolato dal subscriber
                return null;
            }
        );
    }

    public function setCachedProductData(int $productId, array $data): void
    {
        $this->cache->delete("klaviyo.product.{$productId}");
        
        $this->cache->get(
            "klaviyo.product.{$productId}",
            function (ItemInterface $item) use ($data) {
                $item->expiresAfter(3600);
                return $data;
            }
        );
    }
}
```

---

## 🚀 Deployment e Monitoring

### Environment Configuration

```yaml
# config/packages/prod/klaviyo.yaml
parameters:
    klaviyo.enabled: true
    klaviyo.batch_size: 100
    klaviyo.timeout: 15
    klaviyo.max_retries: 3

services:
    App\Service\KlaviyoService:
        arguments:
            $timeout: '%klaviyo.timeout%'
            $maxRetries: '%klaviyo.max_retries%'
```

### Monitoring Command

```php
<?php
// src/Command/KlaviyoHealthCheckCommand.php

namespace App\Command;

use App\Service\KlaviyoService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'klaviyo:health-check')]
class KlaviyoHealthCheckCommand extends Command
{
    public function __construct(private KlaviyoService $klaviyoService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $testEvent = [
            'event' => 'Health Check',
            'properties' => ['test' => true],
            'customer' => ['email' => 'test@example.com']
        ];

        $success = $this->klaviyoService->trackEvent($testEvent);

        if ($success) {
            $output->writeln('<info>✅ Klaviyo service is healthy</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>❌ Klaviyo service is down</error>');
            return Command::FAILURE;
        }
    }
}
```

### Cron Jobs

```bash
# crontab -e

# Sync catalogo ogni notte alle 2:00
0 2 * * * /usr/bin/php /path/to/sylius/bin/console klaviyo:sync-catalog --batch-size=100

# Health check ogni 15 minuti
*/15 * * * * /usr/bin/php /path/to/sylius/bin/console klaviyo:health-check

# Cleanup failed jobs ogni giorno
0 3 * * * /usr/bin/php /path/to/sylius/bin/console messenger:failed:remove --force
```

---

## 🐛 Testing

### Unit Tests

```php
<?php
// tests/Unit/Service/KlaviyoServiceTest.php

namespace App\Tests\Unit\Service;

use App\Service\KlaviyoService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KlaviyoServiceTest extends TestCase
{
    public function testTrackEventSuccess(): void
    {
        $mockResponse = new MockResponse('{"success": true}', ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);

        $klaviyoService = new KlaviyoService($httpClient, $this->createMock(ParameterBagInterface::class), $this->createMock(LoggerInterface::class));

        $eventData = [
            'event' => 'Test Event',
            'properties' => ['test' => true]
        ];

        $result = $klaviyoService->trackEvent($eventData);

        $this->assertTrue($result);
    }

    public function testTrackEventFailure(): void
    {
        $mockResponse = new MockResponse('{"error": "Bad Request"}', ['http_code' => 400]);
        $httpClient = new MockHttpClient($mockResponse);

        $klaviyoService = new KlaviyoService($httpClient, $this->createMock(ParameterBagInterface::class), $this->createMock(LoggerInterface::class));

        $eventData = [
            'event' => 'Test Event',
            'properties' => ['test' => true]
        ];

        $result = $klaviyoService->trackEvent($eventData);

        $this->assertFalse($result);
    }
}
```

### Integration Tests

```php
<?php
// tests/Integration/EventSubscriber/KlaviyoProductViewSubscriberTest.php

namespace App\Tests\Integration\EventSubscriber;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class KlaviyoProductViewSubscriberTest extends WebTestCase
{
    public function testProductViewTracking(): void
    {
        $client = static::createClient();
        
        // Mock del KlaviyoService
        $klaviyoService = $this->createMock(KlaviyoService::class);
        $klaviyoService->expects($this->once())
            ->method('trackEvent')
            ->with($this->callback(function ($eventData) {
                return $eventData['event'] === 'Viewed Product' &&
                       isset($eventData['properties']['product_id']);
            }))
            ->willReturn(true);

        self::getContainer()->set(KlaviyoService::class, $klaviyoService);

        // Visita pagina prodotto
        $client->request('GET', '/en_US/products/test-product');

        $this->assertResponseIsSuccessful();
    }
}
```

---

## 🔐 Security Best Practices

### API Key Rotation

```php
<?php
// src/Service/KlaviyoSecurityService.php

namespace App\Service;

class KlaviyoSecurityService
{
    public function rotateApiKey(string $newApiKey): void
    {
        // Valida nuovo API key
        if (!$this->validateApiKey($newApiKey)) {
            throw new \InvalidArgumentException('Invalid API key format');
        }

        // Test connessione
        if (!$this->testApiKey($newApiKey)) {
            throw new \RuntimeException('API key test failed');
        }

        // Aggiorna configurazione
        $this->updateApiKeyConfiguration($newApiKey);

        // Log della rotazione
        $this->logApiKeyRotation();
    }

    private function validateApiKey(string $apiKey): bool
    {
        return preg_match('/^pk_[a-zA-Z0-9]{32}$/', $apiKey);
    }
}
```

### Rate Limiting

```php
<?php
// src/Service/KlaviyoRateLimiter.php

namespace App\Service;

use Symfony\Component\RateLimiter\RateLimiterFactory;

class KlaviyoRateLimiter
{
    private RateLimiterFactory $rateLimiterFactory;

    public function __construct(RateLimiterFactory $rateLimiterFactory)
    {
        $this->rateLimiterFactory = $rateLimiterFactory;
    }

    public function canMakeRequest(): bool
    {
        $limiter = $this->rateLimiterFactory->create('klaviyo_api');
        return $limiter->consume()->isAccepted();
    }
}
```

---

## 📋 Troubleshooting Common Issues

### 1. Eventi Non Tracciati

**Sintomi:**
- Eventi non appaiono in Klaviyo
- Log errors 400/401

**Soluzioni:**
```bash
# Verifica configurazione
php bin/console debug:config klaviyo

# Test API key
php bin/console klaviyo:health-check

# Verifica log
tail -f var/log/klaviyo.log
```

### 2. Performance Issues

**Sintomi:**
- Pagine lente
- Timeout HTTP

**Soluzioni:**
```php
// Implementa circuit breaker
if ($this->consecutiveFailures > 5) {
    $this->logger->warning('Klaviyo circuit breaker OPEN');
    return false;
}
```

### 3. Memory Leaks

**Sintomi:**
- Memory usage alto nei comandi
- Worker crashes

**Soluzioni:**
```php
// Cleanup periodico
if ($this->processedEvents % 100 === 0) {
    gc_collect_cycles();
    $this->entityManager->clear();
}
```

---

Questa guida fornisce una implementazione completa e production-ready per integrare Sylius con Klaviyo attraverso il microservizio Laravel, con focus su performance, scalabilità e affidabilità.