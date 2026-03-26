<?php
namespace DynamicFieldConfig;

use MapasCulturais\App;
use MapasCulturais\i;

class Plugin extends \MapasCulturais\Plugin {
    
    public function _init() {
        $app = App::i();
        
        // Hook after registration to modify metadata (for labels and required)
        $self = $this;
        $app->hook('app.register:after', function() use($app, $self) {
            $self->applyFieldOverrides();
        });

        // Hook into metadata params to hide dynamically registered fields
        $entities = ['Agent', 'Space', 'Project', 'Event', 'Opportunity'];
        foreach ($entities as $entity) {
            $app->hook("entity($entity).metadata.params", function(&$params) use($entity) {
                $this->filterMetadataParams($params, "MapasCulturais\\Entities\\$entity");
            });
            
            // Inyectar JS en vistas de entidad: tanto single (solo ver) como edit (editar)
            // Solo se registra el hook una vez para Agent, e.g., luego dentro del callback
            // usamos el controller actual para saber para qué entidad inyectar.
        }
        
        // Hook genérico en el body que funciona en CUALQUIER página de entidad
        // (independiente del nombre de la ruta o alias de URL)
        $entityClassByController = [
            'agent'       => 'MapasCulturais\\Entities\\Agent',
            'space'       => 'MapasCulturais\\Entities\\Space',
            'project'     => 'MapasCulturais\\Entities\\Project',
            'event'       => 'MapasCulturais\\Entities\\Event',
            'opportunity' => 'MapasCulturais\\Entities\\Opportunity',
        ];
        $app->hook('mapasculturais.body:after', function() use($app, $self, $entityClassByController) {
            $controller = $app->view->controller ?? null;
            if (!$controller) return;
            
            $controllerId = strtolower($controller->id ?? '');
            $action = strtolower($controller->action ?? '');
            
            if (!in_array($action, ['edit', 'create', 'single'])) return;
            
            $entityClass = $entityClassByController[$controllerId] ?? null;
            if (!$entityClass) return;
            
            $self->injectFrontendOverrides($entityClass);
        });

        // Register Admin Controller route assets
        $app->hook('GET(dynamic-field-config.index)', function () use ($app) {
            $app->view->enqueueStyle('app', 'dynamic-field-config', 'css/dynamic-field-config.css');
        });

        // Add link to panel nav
        $app->hook('panel.nav', function (&$nav_items) use ($app) {
            if ($app->user->is('admin')) {
                $nav_items['admin']['items'][] = [
                    'route'  => 'dynamic-field-config/index',
                    'icon'   => 'config',
                    'label'  => i::__('Configurar Campos'),
                ];
            }
        });

        // Registrar título para el controller custom
        $app->hook('view.title(dynamic-field-config.index)', function (&$title) {
            $title = i::__('Configurar Campos') . ' - ' . App::i()->siteName;
        });
    }

    public function register() {
        $app = App::i();
        $app->registerController('dynamic-field-config', 'DynamicFieldConfig\Controllers\Admin');
    }

    private function applyFieldOverrides() {
        $app = App::i();
        $configFile = BASE_PATH . 'files/config/field-overrides.json';

        if (!file_exists($configFile)) {
            return;
        }

        $json = file_get_contents($configFile);
        $overrides = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($overrides)) {
            return;
        }
        
        foreach ($overrides as $entityClass => $fields) {
            if (!is_array($fields)) continue;
            
            foreach ($fields as $key => $config) {
                 $def = $app->getRegisteredMetadataByMetakey($key, $entityClass);
                 
                 // Solo aplica a metadatos dinámicos. Las nativas se tratan en JS.
                 if ($def) {
                     if (!empty($config['label'])) {
                         $def->label = $config['label'];
                     }
                      if (isset($config['required'])) {
                          $isEnabled = !isset($config['enabled']) || ($config['enabled'] != 0 && $config['enabled'] !== false);
                          if (($config['required'] == 1 || $config['required'] === true) && $isEnabled) {
                              $def->validations['required'] = i::__('Este campo es obligatorio.');
                          } else {
                              unset($def->validations['required']);
                          }
                      }
                 }
            }
        }
    }

    private function filterMetadataParams(&$params, $entityClass) {
        $configFile = BASE_PATH . 'files/config/field-overrides.json';
        if (!file_exists($configFile)) {
            return;
        }

        $json = file_get_contents($configFile);
        $overrides = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($overrides) || !isset($overrides[$entityClass])) {
            return;
        }

        $entityOverrides = $overrides[$entityClass];

        foreach ($params as $key => $def) {
            if (isset($entityOverrides[$key]['enabled']) && ($entityOverrides[$key]['enabled'] == 0 || $entityOverrides[$key]['enabled'] === false)) {
                unset($params[$key]);
            }
        }
    }
    
    /**
     * Inyecta JS en el frontend para ocultar, requerir o renombrar 
     * campos NATIVOS (como shortDescription, name, biografia) directamente manipulando el DOM
     * ya que los hooks en backend no afectan al HTML estático del template.
     */
    public function injectFrontendOverrides($entityClass) {
        $app = App::i();
        $configFile = BASE_PATH . 'files/config/field-overrides.json';
        if (!file_exists($configFile)) {
            return;
        }
        
        $json = file_get_contents($configFile);
        $overrides = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($overrides) || empty($overrides[$entityClass])) {
            return;
        }
        
        $entityOverrides = $overrides[$entityClass];
        // Filtrar qué overrides pasar al frontend JS (para no pasar todo)
        $jsConfig = [];
        
        // Lista de propiedades nativas comunes (añadimos namerelation, geoPais, etc)
        $nativeProperties = [
            'name', 'shortDescription', 'longDescription', 'En_Estado', 'namerelation', 'terms', 'location', 
            'site', 'emailPrivate', 'emailPublic', 'telefone1', 'telefone2', 'telefone3'
        ];
        
        foreach ($entityOverrides as $key => $config) {
            // Pasamos al front todos, porque incluso un metadata label o hidden extra podría arreglarse en JS
            $jsConfig[$key] = [
                'enabled' => $config['enabled'] ?? true,
                'required' => $config['required'] ?? false,
                'label'   => $config['label'] ?? ''
            ];
        }
        
        // Renderizar el bloque JS
        $jsonPayload = json_encode($jsConfig, JSON_UNESCAPED_UNICODE);
        
        echo <<<HTML
        <!-- DYNAMIC FIELD CONFIG ENFORCER v2.1 -->
        <script>
        (function() {
            var overrides = {$jsonPayload};
            console.log("DFC INJECTED FOR:", "{$entityClass}", overrides);
            
            var applyTimeout;
            function debounceApply() {
                if (applyTimeout) clearTimeout(applyTimeout);
                applyTimeout = setTimeout(applyDOMOverrides, 300);
            }
            
            // Usamos MutationObserver para no cargar la CPU con setIntervals
            var observer = new MutationObserver(function(mutations) {
                if (document.querySelector('body.is-editable') || document.querySelector('body.user-is-admin')) {
                    debounceApply();
                }
            });
            
            window.addEventListener('load', function() {
                observer.observe(document.body, { childList: true, subtree: true });
                debounceApply(); // Primera ejecución al cargar
            });
            
            function applyDOMOverrides() {
                if (!MapasCulturais || !MapasCulturais.entity || !MapasCulturais.isEditable) return;
                
                for (var key in overrides) {
                    var conf = overrides[key];
                    var elements = [];
                    
                    // 1. Campos x-editable directos
                    document.querySelectorAll('[data-edit="' + key + '"]').forEach(el => elements.push(el));
                    
                    // 2. Metadatos dinámicos
                    document.querySelectorAll('.metadata-item[data-metakey="' + key + '"]').forEach(el => elements.push(el));
                    
                    // 3. Inputs nativos
                    document.querySelectorAll('input[name="' + key + '"], textarea[name="' + key + '"], select[name="' + key + '"]').forEach(el => elements.push(el));
                    
                    // 4. Casos Especiales de Bloques Compuestos (Mapas)
                    if (key === 'location' || key === 'address' || key === 'En_Estado') {
                        var mapContainer = document.querySelector('.map-container') || document.querySelector('.js-map-container');
                        if (mapContainer) elements.push(mapContainer);
                        
                        document.querySelectorAll('.address-form, .js-endereco-form').forEach(el => elements.push(el));
                        
                        var mapLabels = Array.from(document.querySelectorAll('h2, h3, h4, .label')).filter(l => l.innerText.trim().toLowerCase() === 'dirección' || l.innerText.trim().toLowerCase() === 'endereco');
                        mapLabels.forEach(l => {
                            if(l.parentElement) elements.push(l.parentElement);
                        });
                        
                        // Si es En_Estado, buscar selector especifico
                        if (key === 'En_Estado') {
                            document.querySelectorAll('[data-edit="En_Estado"]').forEach(el => elements.push(el));
                        }
                    }
                    
                    // Asegurar array único
                    elements = Array.from(new Set(elements));
                    
                    elements.forEach(function(el) {
                        var container = el.closest('.form-item, label, .metadata-item, p, .address-form, .map-container') || el;
                        
                        // HIDDEN
                        if (conf.enabled === false || conf.enabled === "0") {
                            if(container && container.style.display !== 'none') container.style.setProperty('display', 'none', 'important');
                            if(el && el.style.display !== 'none') el.style.setProperty('display', 'none', 'important');
                            
                            var prev = container ? container.previousElementSibling : null;
                            if (prev && (prev.tagName === 'H2' || prev.tagName === 'H3' || prev.className.includes('label'))) {
                                if (prev.style.display !== 'none') prev.style.setProperty('display', 'none', 'important');
                            }
                        }
                        
                        // LABEL
                        if (conf.label && conf.label.trim() !== "") {
                            var labelEl = container ? (container.querySelector('.label') || container.querySelector('h2') || container.querySelector('strong')) : null;
                            
                            if (!labelEl && container && container.previousElementSibling) {
                                var prevTag = container.previousElementSibling.tagName;
                                if (prevTag === 'H2' || prevTag === 'H3' || prevTag === 'LABEL' || container.previousElementSibling.classList.contains('label')) {
                                    labelEl = container.previousElementSibling;
                                }
                            }
                            
                            if (labelEl) {
                                var hasRequiredMark = labelEl.querySelector('.required-mark') !== null;
                                labelEl.childNodes.forEach(child => {
                                   if(child.nodeType === Node.TEXT_NODE && child.textContent.trim().length > 0 && child.textContent.trim() !== conf.label) {
                                       child.textContent = conf.label;
                                   } 
                                });
                                if(labelEl.childNodes.length === 0 || (labelEl.childNodes.length === 1 && labelEl.childNodes[0].nodeType !== Node.TEXT_NODE && !hasRequiredMark)) {
                                    if (labelEl.textContent !== conf.label) labelEl.textContent = conf.label; 
                                }
                            }
                            
                            if (el && el.hasAttribute('data-original-title') && el.getAttribute('data-original-title') !== conf.label) {
                                el.setAttribute('data-original-title', conf.label);
                                el.setAttribute('title', conf.label);
                            }
                        }
                        
                        // REQUIRED
                        if ((conf.required === true || conf.required === "1") && conf.enabled !== false && conf.enabled !== "0" && conf.enabled !== 0) {
                            var targetLabel = container ? (container.querySelector('.label') || container.querySelector('h2')) : null;
                            
                            if (!targetLabel && container && container.previousElementSibling) {
                                var prevTag2 = container.previousElementSibling.tagName;
                                if (prevTag2 === 'H2' || prevTag2 === 'H3' || prevTag2 === 'LABEL' || container.previousElementSibling.classList.contains('label')) {
                                    targetLabel = container.previousElementSibling;
                                }
                            }
                            
                            if (targetLabel && !targetLabel.querySelector('.required-mark')) {
                                var marker = document.createElement('span');
                                marker.className = 'required-mark';
                                marker.style.color = 'red';
                                marker.textContent = ' *';
                                targetLabel.appendChild(marker);
                            }
                            
                            // Inyectar a nivel de JS
                            if (MapasCulturais.entity && MapasCulturais.entity.metadata) {
                                if (MapasCulturais.entity.metadata[key] && MapasCulturais.entity.metadata[key].validations) {
                                    if (MapasCulturais.entity.metadata[key].validations.required !== "Este campo es obligatorio.") {
                                        MapasCulturais.entity.metadata[key].validations.required = "Este campo es obligatorio.";
                                    }
                                } else {
                                    MapasCulturais.entity[key] = MapasCulturais.entity[key] || {};
                                    if(typeof MapasCulturais.entity[key] === 'object') {
                                        MapasCulturais.entity[key].validations = MapasCulturais.entity[key].validations || {};
                                        MapasCulturais.entity[key].validations.required = "Este campo es obligatorio.";
                                    }
                                }
                            }
                        }
                    });
                }
            }
            
            if (window.jQuery) {
                jQuery(document).ajaxComplete(function() {
                    debounceApply();
                });
            }
            
            // Interceptar el botón guardar para validar campos nativos requeridos
            function setupSaveInterception() {
                // El botón guardar en este tema es button.publish / button.publish-exit
                var saveButtons = document.querySelectorAll('button.publish, button.publish-exit, .save-button, .js-save');
                saveButtons.forEach(function(btn) {
                    if (btn.dataset.dfcIntercepted) return;
                    btn.dataset.dfcIntercepted = '1';
                    btn.addEventListener('click', function(e) {
                        var missingFields = [];
                        for (var key in overrides) {
                            var conf = overrides[key];
                            if ((conf.required === true || conf.required === "1") && conf.enabled !== false && conf.enabled !== "0" && conf.enabled !== 0) {
                                var currentValue = '';
                                
                                // 1. Primero buscar como textarea/input nativo (Vue.js usa inputs reales)
                                var inputEl = document.querySelector('textarea[name="' + key + '"], input[name="' + key + '"]');
                                if (inputEl) {
                                    currentValue = (inputEl.value || '').trim();
                                } else {
                                    // 2. Fallback: x-editable span
                                    var editEl = document.querySelector('[data-edit="' + key + '"]');
                                    if (editEl) {
                                        currentValue = (editEl.textContent || editEl.innerText || '').trim();
                                        if (currentValue === 'empty' || currentValue === 'vazio' || currentValue === '-') {
                                            currentValue = '';
                                        }
                                    }
                                }
                                
                                if (!currentValue) {
                                    var labelText = conf.label || key;
                                    missingFields.push(labelText);
                                }
                            }
                        }
                        if (missingFields.length > 0) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            alert('Los siguientes campos son obligatorios:\n- ' + missingFields.join('\n- '));
                            return false;
                        }
                    }, true); // capture=true para interceptar antes que Vue/Angular
                });
            }
            
            // Configurar interceptor cuando la página esté lista
            window.addEventListener('load', function() {
                setTimeout(setupSaveInterception, 1500); // Dar tiempo a Vue para renderizar
            });
        })();
        </script>
HTML;
    }
}
