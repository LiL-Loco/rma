# JTL Demo-Plugin - Plugin-Struktur Analyse

**Datum:** 17. Oktober 2025  
**Analysiert von:** Development Team  
**Quelle:** https://gitlab.com/jtl-software/jtl-shop/plugins/jtl_test.git

---

## Übersicht

Das **JTL Test Plugin** dient als **Referenz-Implementierung** für JTL Shop 5+ Plugins und demonstriert alle wichtigen Features:

- ✅ Plugin-Architektur & Bootstrap
- ✅ Admin-Bereich mit Tabs
- ✅ Frontend-Controller & Routing
- ✅ Database Migrations
- ✅ Model-basierte Datenverwaltung
- ✅ Event-System (Hooks)
- ✅ Smarty-Integration
- ✅ Consent Manager Integration
- ✅ Cron-Jobs
- ✅ E-Mail-Templates

---

## Ordnerstruktur

```
jtl_test/
├── Bootstrap.php                    # Haupt-Bootstrap-Klasse
├── info.xml                         # Plugin-Manifest
├── DemoController.php               # Frontend-Controller (Routing)
├── ModelBackendController.php       # Admin-Controller (CRUD)
├── TestHelper.php                   # Helper-Klasse
├── TestCronJob.php                  # Cron-Job-Klasse
│
├── adminmenu/                       # Admin-Bereich
│   ├── templates/                   # Admin-Templates
│   │   ├── testtab.tpl
│   │   ├── tab2.tpl
│   │   └── models.tpl
│   ├── widget/                      # Dashboard-Widgets
│   │   └── TestWidget.php
│   ├── jtl_test_selectsource1.php   # Dynamische Selectbox-Optionen
│   ├── jtl_test_selectsource2.php
│   └── jtl_test_radiosource1.php
│
├── frontend/                        # Frontend-Bereich
│   ├── template/                    # Frontend-Templates
│   │   ├── test_page.tpl
│   │   ├── test_page_bootstrap.tpl
│   │   ├── test_page_fullscreen.tpl
│   │   └── routed.tpl
│   ├── boxes/                       # Box-Templates
│   │   └── example_box.tpl
│   ├── css/                         # CSS-Dateien
│   │   ├── foo.css
│   │   └── bar.css
│   ├── js/                          # JavaScript-Dateien
│   │   ├── foo.js
│   │   └── bar.js
│   ├── test_page.php                # Legacy-Controller
│   └── test_page_fullscreen.php
│
├── Models/                          # Domain-Models
│   ├── ModelFoo.php
│   ├── ModelBar.php
│   └── ModelItem.php
│
├── Migrations/                      # Datenbank-Migrationen
│   ├── Migration20181112155500.php
│   ├── Migration20181127162200.php
│   └── Migration20220908154300.php
│
├── Smarty/                          # Smarty-Erweiterungen
│   ├── Registrator.php
│   ├── Modifier/
│   ├── Plugin/
│   └── Block/
│
├── locale/                          # Übersetzungen
│   ├── de-DE/
│   └── en-GB/
│
├── preview.png                      # Plugin-Icon
├── README.md                        # Dokumentation
└── LICENSE.md                       # Lizenz
```

---

## 1. info.xml (Plugin-Manifest)

**Zweck:** Metadaten, Installation, Konfiguration

### Basis-Metadaten

```xml
<jtlshopplugin>
    <Name>JTL Test</Name>
    <Description>Beispiel-Plugin für JTL-Shop 5+</Description>
    <Author>Felix Moche</Author>
    <URL>https://www.jtl-software.de</URL>
    <PluginID>jtl_test</PluginID>
    <Version>1.2.0</Version>
    <ShopVersion>5.0.0</ShopVersion>
    <XMLVersion>100</XMLVersion>
    <CreateDate>2024-07-05</CreateDate>
    <Icon>preview.png</Icon>
</jtlshopplugin>
```

### Cache-Flush bei Installation

```xml
<Install>
    <FlushTags>CACHING_GROUP_CATEGORY, CACHING_GROUP_ARTICLE</FlushTags>
</Install>
```

**Anwendung:** Löscht relevante Caches nach Plugin-Installation

### Frontend-Links

```xml
<FrontendLink>
    <Link>
        <Filename>-</Filename>  <!-- "-" = Bootstrap-Controller -->
        <Name>JTL Bootstrap Example Page</Name>
        <FullscreenTemplate>test_page_bootstrap.tpl</FullscreenTemplate>
        <VisibleAfterLogin>N</VisibleAfterLogin>
        <SSL>2</SSL>  <!-- 0=HTTP, 1=HTTPS, 2=Beide -->
        <LinkGroup>Fuss</LinkGroup>  <!-- Footer-Navigation -->
        <LinkLanguage iso="GER">
            <Seo>jtl-test-bootstrap-page</Seo>
            <Name>TestBootstrapPage</Name>
            <Title>TestBootstrapPage</Title>
            <MetaTitle>TestPage Meta Title Bootstrap</MetaTitle>
            <MetaKeywords>Test,Page,Meta,Keyword,Bootstrap</MetaKeywords>
            <MetaDescription>BootstrapTestPage Meta Description</MetaDescription>
        </LinkLanguage>
    </Link>
    
    <!-- Legacy: PHP-Datei als Controller -->
    <Link>
        <Filename>test_page.php</Filename>
        <Template>test_page.tpl</Template>
        <LinkLanguage iso="GER">
            <Seo>jtl-test-page</Seo>
            <Name>TestPage</Name>
        </LinkLanguage>
    </Link>
</FrontendLink>
```

**URL-Generierung:**
```
https://shop.de/jtl-test-bootstrap-page
https://shop.de/jtl-test-page
```

### Admin-Menü

```xml
<Adminmenu>
    <!-- Custom-Tabs -->
    <Customlink sort="1">
        <Name>Ein Testtab</Name>
    </Customlink>
    <Customlink sort="2">
        <Name>Tab2</Name>
    </Customlink>
    <Customlink sort="3">
        <Name>Models</Name>
    </Customlink>
    
    <!-- Settings-Tab -->
    <Settingslink sort="4">
        <Name>Setting Tests</Name>
        
        <!-- Text-Feld -->
        <Setting type="text" initialValue="1" conf="Y">
            <Name>Text Test</Name>
            <Description>Beschreibung für Text Test</Description>
            <ValueName>jtl_test_1</ValueName>
        </Setting>
        
        <!-- Selectbox -->
        <Setting type="selectbox" initialValue="1" conf="Y">
            <Name>Selectbox Test</Name>
            <ValueName>jtl_test_2</ValueName>
            <OptionsSource>
                <File>jtl_test_selectsource1.php</File>
            </OptionsSource>
            <SelectboxOptions>
                <Option value="0" sort="1">Option 1</Option>
                <Option value="1" sort="2">Option 2</Option>
            </SelectboxOptions>
        </Setting>
        
        <!-- Textarea -->
        <Setting type="textarea" initialValue="Hallo Welt!" conf="Y">
            <Name>Textarea Test</Name>
            <ValueName>jtl_test_3</ValueName>
        </Setting>
        
        <!-- Checkbox -->
        <Setting type="checkbox" initialValue="on" conf="Y">
            <Name>Checkbox Test</Name>
            <ValueName>jtl_test_4</ValueName>
        </Setting>
        
        <!-- Radio-Buttons -->
        <Setting type="radio" initialValue="Y" conf="Y">
            <Name>Radio Test</Name>
            <ValueName>jtl_test_6b</ValueName>
            <RadioOptions>
                <Option value="X" sort="1">X</Option>
                <Option value="Y" sort="2">Y</Option>
                <Option value="Z" sort="3">Z</Option>
            </RadioOptions>
        </Setting>
        
        <!-- Multi-Selectbox -->
        <Setting type="selectbox" initialValue="D" conf="Y" multiple="Y">
            <Name>Selectbox multiple Test</Name>
            <ValueName>jtl_test_multi_select</ValueName>
            <SelectboxOptions>
                <Option value="A">aaaaa</Option>
                <Option value="B">bbbbb</Option>
            </SelectboxOptions>
        </Setting>
        
        <!-- Color-Picker -->
        <Setting type="colorpicker" initialValue="#ffff00" conf="Y">
            <Name>Farbe</Name>
            <ValueName>jtl_color_1</ValueName>
        </Setting>
        
        <!-- Email -->
        <Setting type="email" initialValue="" conf="Y">
            <Name>Email Test</Name>
            <ValueName>jtl_test_email</ValueName>
        </Setting>
        
        <!-- Date/Time -->
        <Setting type="date" initialValue="1" conf="Y">
            <Name>Date Test</Name>
            <ValueName>jtl_test_date</ValueName>
        </Setting>
        
        <Setting type="time" initialValue="13:37" conf="Y">
            <Name>Time Test</Name>
            <ValueName>jtl_test_time</ValueName>
        </Setting>
        
        <!-- Range-Slider -->
        <Setting type="range" initialValue="42" conf="Y" min="22" max="77">
            <Name>Range Test</Name>
            <ValueName>jtl_test_range</ValueName>
        </Setting>
        
        <!-- URL -->
        <Setting type="url" initialValue="https://example.com" conf="Y">
            <Name>URL Test</Name>
            <ValueName>jtl_test_url</ValueName>
        </Setting>
    </Settingslink>
</Adminmenu>
```

**Config-Zugriff:**
```php
$value = $this->getPlugin()->getConfig()->getValue('jtl_test_1');
```

### E-Mail-Templates

```xml
<Emailtemplate>
    <Template>
        <Name>Testmail</Name>
        <Description>Email für Testzwecke</Description>
        <Type>text/html</Type>
        <ModulId>jtltest</ModulId>
        <Active>Y</Active>
        <TemplateLanguage iso="GER">
            <Subject>Testmail Betreff</Subject>
            <ContentHtml><![CDATA[
{includeMailTemplate template=header type=html}
<p>Hallo, dies ist eine <strong>Testemail</strong>.</p>
{includeMailTemplate template=footer type=html}
            ]]></ContentHtml>
            <ContentText><![CDATA[
Hallo, dies ist eine Testemail.
Ihr Team von {$Firma->cName}
            ]]></ContentText>
        </TemplateLanguage>
    </Template>
</Emailtemplate>
```

### CSS/JS-Integration

```xml
<CSS>
    <file>
        <name>foo.css</name>
        <priority>4</priority>  <!-- Höher = später geladen -->
    </file>
</CSS>

<JS>
    <file>
        <name>foo.js</name>
        <priority>8</priority>
        <position>body</position>  <!-- head oder body -->
    </file>
</JS>
```

**Pfad:** `plugins/jtl_test/frontend/css/foo.css`

### Lokalisierte Variablen

```xml
<Locales>
    <Variable>
        <VariableLocalized iso="GER">PI ist %s und Parameter 2 lautet: %s.</VariableLocalized>
        <VariableLocalized iso="ENG">PI is %s and parameter 2 has the value: %s.</VariableLocalized>
        <Name>xmlp_lang_var_1</Name>
        <Type>textarea</Type>
    </Variable>
</Locales>
```

**Smarty-Nutzung:**
```smarty
{lang key='xmlp_lang_var_1' printf='3.14,Test'}
```

### Boxes (Widgets)

```xml
<Boxes>
    <Box>
        <Name>ExampleBoxFromExamplePlugin</Name>
        <Available>0</Available>  <!-- 0=Deaktiviert, 1=Aktiviert -->
        <TemplateFile>example_box.tpl</TemplateFile>
    </Box>
</Boxes>
```

### Admin-Widgets

```xml
<AdminWidget>
    <Widget>
        <Title>Title from NEW extension</Title>
        <Class>TestWidget</Class>
        <Container>center</Container>  <!-- left, center, right -->
        <Description>Example description</Description>
        <Pos>4</Pos>
        <Expanded>1</Expanded>
        <Active>1</Active>
    </Widget>
</AdminWidget>
```

---

## 2. Bootstrap.php (Haupt-Klasse)

**Zweck:** Plugin-Initialisierung, Event-Listener, Hooks

### Klassen-Struktur

```php
namespace Plugin\jtl_test;

use JTL\Plugin\Bootstrapper;
use JTL\Events\Dispatcher;

class Bootstrap extends Bootstrapper
{
    private ?TestHelper $helper = null;
    
    public function boot(Dispatcher $dispatcher): void
    public function installed(): void
    public function updated($oldVersion, $newVersion): void
    public function uninstalled(bool $deleteData = true): void
    public function prepareFrontend(LinkInterface $link, JTLSmarty $smarty): bool
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
}
```

### Boot-Methode (Event-Listener)

```php
public function boot(Dispatcher $dispatcher): void
{
    parent::boot($dispatcher);
    
    // 1. Cron-Job-Mapping
    $dispatcher->listen(Event::MAP_CRONJOB_TYPE, static function (array &$args) {
        if ($args['type'] === 'jtl_test_cron') {
            $args['mapping'] = TestCronJob::class;
        }
    });
    
    // 2. Frontend-Only
    if (Shop::isFrontend() === false) {
        return;
    }
    
    $plugin = $this->getPlugin();
    
    // 3. Hook: CSS/JS geladen
    $dispatcher->listen('shop.hook.' . \HOOK_LETZTERINCLUDE_CSS_JS, static function () {
        Shop::set('jtl_test_foo', 42);  // Registry-Wert setzen
    });
    
    // 4. Hook: Produkt modifizieren (vor Cache)
    if ($plugin->getConfig()->getValue('modify_products') === 'Y') {
        $dispatcher->listen(
            'shop.hook.' . \HOOK_ARTIKEL_CLASS_FUELLEARTIKEL,
            function (array &$args) use ($plugin) {
                if ($args['cached'] === false) {
                    $this->modifyProduct($args['oArtikel']);
                    $args['cacheTags'][] = $plugin->getCache()->getGroup();
                }
            },
            1  // Priorität: 1 = hoch (früh)
        );
    }
    
    // 5. Consent Manager Integration
    if ($plugin->getConfig()->getValue('jtl_test_add_consent_item') === 'Y') {
        $dispatcher->listen(
            'shop.hook.' . \CONSENT_MANAGER_GET_ACTIVE_ITEMS,
            [$this, 'addConsentItem']
        );
    }
    
    // 6. Custom Router (Shop 5.2+)
    if (\defined('HOOK_ROUTER_PRE_DISPATCH')) {
        $dispatcher->listen('shop.hook.' . \HOOK_ROUTER_PRE_DISPATCH, function (array $args) {
            $router = $args['router'];
            $controller = new DemoController(...);
            $router->addRoute('/foolist[/{slug}]', [$controller, 'getResponse'], 'demoRoute');
        });
    }
    
    // 7. Smarty-Erweiterungen
    $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_INC, function (array $args) {
        $smarty = $args['smarty'];
        $registrator = new Registrator($smarty, $this->getPlugin());
        $registrator->registerModifier()
            ->registerPlugin()
            ->registerPhpFunctions();
    });
}
```

### Wichtige Hooks

| Hook-Konstante | Zeitpunkt | Use Case |
|----------------|-----------|----------|
| `HOOK_LETZTERINCLUDE_INC` | Vor Template-Render | Smarty-Variablen setzen |
| `HOOK_LETZTERINCLUDE_CSS_JS` | CSS/JS geladen | Registry-Werte |
| `HOOK_ARTIKEL_CLASS_FUELLEARTIKEL` | Produkt geladen | Produktdaten modifizieren |
| `HOOK_KATEGORIE_CLASS_LOADFROMDB` | Kategorie geladen | Kategoriedaten modifizieren |
| `HOOK_SMARTY_INC` | Smarty initialisiert | Modifier/Plugins registrieren |
| `HOOK_ROUTER_PRE_DISPATCH` | Vor Routing | Custom Routes |
| `CONSENT_MANAGER_GET_ACTIVE_ITEMS` | Consent Items laden | DSGVO-Consent |

### Lifecycle-Methoden

#### installed()
```php
public function installed(): void
{
    parent::installed();
    
    // Cron-Job hinzufügen
    $job = new \stdClass();
    $job->name = 'Example cron';
    $job->jobType = 'jtl_test_cron';
    $job->frequency = 24;  // Stunden
    $job->startDate = 'NOW()';
    $job->startTime = '00:00:00';
    $this->getDB()->insert('tcron', $job);
}
```

#### updated()
```php
public function updated($oldVersion, $newVersion): void
{
    // Migrations laufen automatisch
    // Hier nur spezielle Update-Logik
}
```

#### uninstalled()
```php
public function uninstalled(bool $deleteData = true): void
{
    parent::uninstalled($deleteData);
    
    // Cron-Job entfernen
    $this->getDB()->delete('tcron', 'jobType', 'jtl_test_cron');
    
    // Wenn $deleteData === false → Nur Plugin deaktivieren
}
```

### prepareFrontend()

**Zweck:** Template-Variablen für Bootstrap-Controller-Seiten

```php
public function prepareFrontend(LinkInterface $link, JTLSmarty $smarty): bool
{
    parent::prepareFrontend($link, $smarty);
    
    if ($link->getTemplate() !== 'test_page_bootstrap.tpl') {
        return false;  // Nicht für diese Seite
    }
    
    $smarty->assign('jtl_test_var', 'Hello from ' . __METHOD__)
        ->assign('exampleConfigVars', $this->getPlugin()->getConfig());
    
    return true;
}
```

### renderAdminMenuTab()

**Zweck:** Admin-Tab-Inhalte rendern

```php
public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
{
    $plugin = $this->getPlugin();
    $backendURL = $plugin->getPaths()->getBackendURL();
    
    $smarty->assign('menuID', $menuID);
    
    if ($tabName === 'Ein Testtab') {
        $alert = Shop::Container()->getAlertService();
        
        if (Request::postInt('clear-cache') === 1) {
            if (Form::validateToken()) {
                // CSRF-Token validiert
                $this->getCache()->flushTags($plugin->getCache()->getGroup());
                $alert->addAlert(Alert::TYPE_SUCCESS, 'Cache successfully flushed.');
            } else {
                $alert->addAlert(Alert::TYPE_ERROR, 'CSRF error!');
            }
        }
        
        return $smarty->assign('backendURL', $backendURL)
            ->fetch($plugin->getPaths()->getAdminPath() . '/templates/testtab.tpl');
    }
    
    if ($tabName === 'Models') {
        return $this->renderModelTab($menuID, $smarty);
    }
    
    return '';
}
```

---

## 3. Controller

### Frontend-Controller (DemoController.php)

**Zweck:** PSR-7 Request/Response für Custom Routes

```php
namespace Plugin\jtl_test;

use JTL\Router\Controller\PageController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DemoController extends PageController
{
    public function getResponse(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        $this->smarty = $smarty;
        Shop::setPageType(\PAGE_PLUGIN);
        $this->init();
        $this->preRender();
        
        $link = new Link($this->db);
        $link->setLinkType(\LINKTYP_PLUGIN);
        
        // Model aus Route-Parameter
        $model = null;
        if (!empty($args['slug'])) {
            $model = ModelItem::loadByAttributes(['slug' => $args['slug']], $this->db);
        }
        
        return $this->smarty->assign('Link', $link)
            ->assign('model', $model)
            ->assign('models', $this->getModels())
            ->assign('cPluginTemplate', __DIR__ . '/frontend/template/routed.tpl')
            ->getResponse('layout/index.tpl');
    }
    
    private function getModels(): Collection
    {
        $router = Shop::getRouter();
        $locale = $this->getLocaleFromLanguageID(Shop::getLanguageID());
        
        return ModelItem::loadAll($this->db, [], [])->each(function ($model) use ($router, $locale) {
            $model->setUrl(
                $router->getURLByType(
                    'demoRoute',  // Route-Name aus Bootstrap
                    ['slug' => $model->getSlug(), 'id' => $model->getId(), 'lang' => $locale],
                    true,
                    true
                )
            );
            return $model;
        });
    }
}
```

**Route-Registrierung:**
```php
// In Bootstrap.php
$router->addRoute('/foolist[/{slug}]', [$controller, 'getResponse'], 'demoRoute');
```

**URLs:**
```
/foolist              → Übersicht
/foolist/example      → Detail (slug=example)
```

### Backend-Controller (ModelBackendController.php)

**Zweck:** CRUD-Verwaltung für Models

```php
namespace Plugin\jtl_test;

use JTL\Router\Controller\Backend\GenericModelController;

class ModelBackendController extends GenericModelController
{
    public int $menuID = 0;
    public PluginInterface $plugin;
    
    public function getResponse(
        ServerRequestInterface $request,
        array $args,
        JTLSmarty $smarty
    ): ResponseInterface {
        $this->smarty = $smarty;
        $this->route = \str_replace(Shop::getAdminURL(), '', $this->plugin->getPaths()->getBackendURL());
        $this->modelClass = ModelItem::class;
        $this->adminBaseFile = \ltrim($this->route, '/');
        
        $tab = Request::getVar('action', 'overview');
        
        if ($tab === 'overview') {
            $smarty->assign('models', ModelItem::loadAll($this->getDB(), [], []));
        } else {
            $smarty->assign('item', ModelItem::loadByAttributes(['id' => Request::getInt('id')], $this->getDB()));
        }
        
        $smarty->assign('step', $tab)
            ->assign('action', $this->plugin->getPaths()->getBackendURL());
        
        return $this->handle(__DIR__ . '/adminmenu/templates/models.tpl');
    }
}
```

---

## 4. Models

**Basis:** `JTL\Model\DataModel`

### Beispiel: ModelItem.php

```php
namespace Plugin\jtl_test\Models;

use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

class ModelItem extends DataModel
{
    /**
     * @var int
     */
    #[DataAttribute(
        primaryKey: true,
        write: false
    )]
    protected int $id;
    
    /**
     * @var string
     */
    #[DataAttribute]
    protected string $title = '';
    
    /**
     * @var string
     */
    #[DataAttribute]
    protected string $slug = '';
    
    /**
     * @var string
     */
    #[DataAttribute]
    protected string $description = '';
    
    /**
     * @var string|null - Nicht in DB!
     */
    protected ?string $url = null;
    
    public function getTableName(): string
    {
        return 'jtl_test_items';
    }
    
    // Getter/Setter
    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    // ...
}
```

**CRUD-Operationen:**
```php
// Create
$item = new ModelItem();
$item->setTitle('Example')
    ->setSlug('example')
    ->setDescription('Description');
$item->save();

// Read
$item = ModelItem::load(['id' => 1], $db);
$items = ModelItem::loadAll($db, [], []);  // Collection

// Update
$item->setTitle('New Title');
$item->save();

// Delete
$item->delete();
```

---

## 5. Migrations

**Pfad:** `Migrations/Migration{YYYYMMDDHHmmss}.php`

### Beispiel: Migration20181112155500.php

```php
namespace Plugin\jtl_test\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20181112155500 extends Migration implements IMigration
{
    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `jtl_test_items` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `description` TEXT,
                PRIMARY KEY (`id`),
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    
    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `jtl_test_items`");
    }
}
```

**Ausführung:** Automatisch bei Plugin-Installation/Update

**Reihenfolge:** Timestamp in Dateinamen

---

## 6. Smarty-Integration

### Registrator.php

```php
namespace Plugin\jtl_test\Smarty;

class Registrator
{
    private JTLSmarty $smarty;
    private PluginInterface $plugin;
    
    public function registerModifier(): self
    {
        $this->smarty->registerPlugin(
            SmartyPlugins::PLUGIN_MODIFIER,
            'example_modifier',
            [ExampleModifier::class, 'modify']
        );
        return $this;
    }
    
    public function registerPlugin(): self
    {
        $this->smarty->registerPlugin(
            SmartyPlugins::PLUGIN_FUNCTION,
            'example_function',
            [ExampleFunction::class, 'execute']
        );
        return $this;
    }
    
    public function registerPhpFunctions(): self
    {
        $this->smarty->registerPlugin(
            SmartyPlugins::PLUGIN_MODIFIER,
            'strtoupper',
            'strtoupper'
        );
        return $this;
    }
}
```

**Nutzung im Template:**
```smarty
{$text|example_modifier}
{example_function param="value"}
{$text|strtoupper}
```

---

## 7. Consent Manager (DSGVO)

### Consent Item hinzufügen

```php
public function addConsentItem(array $args): void
{
    $lastID = $args['items']->reduce(static function ($result, Item $item) {
        return max($result, $item->getID());
    }) ?? 0;
    
    $item = new Item();
    $item->setName('JTL Example Consent');
    $item->setID(++$lastID);
    $item->setItemID('jtl_test_consent');
    $item->setDescription('Dies ist nur ein Test');
    $item->setPurpose('Dieser Eintrag dient nur zu Testzwecken');
    $item->setPrivacyPolicy('https://www.jtl-software.de/datenschutz');
    $item->setCompany('JTL-Software-GmbH');
    
    $args['items']->push($item);
}
```

### Consent-Check

```php
$hasConsent = Shop::Container()
    ->getConsentManager()
    ->hasConsent('jtl_test_consent');

if ($hasConsent === true) {
    // User hat zugestimmt
}
```

---

## 8. Cron-Jobs

### TestCronJob.php

```php
namespace Plugin\jtl_test;

use JTL\Cron\Job\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;

class TestCronJob extends Job
{
    public function start(QueueEntry $queueEntry): JobInterface
    {
        // Cron-Logik
        $this->logger->info('Test cron job executed at ' . date('Y-m-d H:i:s'));
        
        return $this;
    }
}
```

### Registration

```php
// In Bootstrap.php
$dispatcher->listen(Event::MAP_CRONJOB_TYPE, static function (array &$args) {
    if ($args['type'] === 'jtl_test_cron') {
        $args['mapping'] = TestCronJob::class;
    }
});
```

---

## 9. Wichtige Services & Container

### Plugin-Services

```php
// DB
$this->getDB()->query(...);

// Cache
$this->getCache()->set('key', 'value');
$this->getCache()->flushTags($this->getPlugin()->getCache()->getGroup());

// Logger
$plugin->getLogger()->info('Message');

// Config
$plugin->getConfig()->getValue('setting_name');
```

### Shop-Container

```php
Shop::Container()->getDB();
Shop::Container()->getCache();
Shop::Container()->getLogService();
Shop::Container()->getAlertService();
Shop::Container()->getConsentManager();
Shop::Container()->getAdminAccount();
Shop::Container()->getGetText();  // Übersetzungen
```

---

## Wiederverwendbarkeit für Customer Returns Plugin

### ✅ Direkt nutzbar

1. **info.xml-Struktur**
   - FrontendLink für `/retoure`
   - Adminmenu mit Settings
   - E-Mail-Templates
   - CSS/JS-Integration

2. **Bootstrap-Pattern**
   - Event-Listener für Hooks
   - prepareFrontend() für Controller
   - renderAdminMenuTab() für Admin

3. **Controller-Architektur**
   - Frontend: PSR-7 PageController
   - Backend: GenericModelController

4. **Model-System**
   - DataModel mit Attributes
   - CRUD-Methoden

5. **Migrations**
   - Timestamp-basierte Versionierung
   - up()/down()-Pattern

### 🔧 Adaptierungen

#### Plugin-ID & Namespace

```xml
<PluginID>jtl_customer_returns</PluginID>
```

```php
namespace Plugin\jtl_customer_returns;
```

#### Frontend-Link

```xml
<Link>
    <Filename>-</Filename>
    <Name>Customer Returns</Name>
    <FullscreenTemplate>return_form.tpl</FullscreenTemplate>
    <LinkLanguage iso="GER">
        <Seo>retoure</Seo>
        <Name>Retoure</Name>
    </LinkLanguage>
</Link>
```

#### Admin-Tabs

```xml
<Customlink sort="1">
    <Name>Retouren-Übersicht</Name>
</Customlink>
<Customlink sort="2">
    <Name>Statistiken</Name>
</Customlink>
```

#### Settings

```xml
<Setting type="number" initialValue="14" conf="Y">
    <Name>Retourenfrist (Tage)</Name>
    <ValueName>return_period_days</ValueName>
</Setting>
<Setting type="checkbox" initialValue="on" conf="Y">
    <Name>Retourenlabel automatisch erstellen</Name>
    <ValueName>auto_create_label</ValueName>
</Setting>
```

---

## Nächste Schritte

1. ✅ **Demo-Plugin analysiert**
2. ⬜ **Plugin-Struktur für Returns anwenden**
3. ⬜ **Datenbank-Schema dokumentieren**
4. ⬜ **User Stories & API-Spec**

---

**Status:** ✅ Abgeschlossen  
**Ergebnis:** JTL Demo-Plugin vollständig analysiert. **Plugin-Architektur** verstanden. **Event-System**, **Routing**, **Models**, **Migrations** dokumentiert. Struktur **wiederverwendbar** für Customer Returns Plugin.
