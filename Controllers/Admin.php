<?php
namespace DynamicFieldConfig\Controllers;

use MapasCulturais\App;
use MapasCulturais\Controller;

class Admin extends Controller {
    
    public function __construct() {
        $this->layout = 'panel';
    }
    
    public function GET_index() {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('admin')) { $app->halt(403); }
        
        // List of entities to configure
        $entities = [
            'MapasCulturais\Entities\Agent' => 'Agentes',
            'MapasCulturais\Entities\Space' => 'Espacios',
            'MapasCulturais\Entities\Project' => 'Proyectos',
            'MapasCulturais\Entities\Event' => 'Eventos',
            'MapasCulturais\Entities\Opportunity' => 'Oportunidades'
        ];
        
        $fieldsConfig = [];
        
        // Load current config
        $configFile = BASE_PATH . 'files/config/field-overrides.json';
        $currentConfig = [];
        if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            $currentConfig = json_decode($json, true) ?: [];
        }
        
        // Criterios de agrupación de campos por categoría
        $groupKeywords = [
            'Identificación'  => ['nomeCompleto','nomeSocial','documento','cpf','cnpj','rg','rne','passaporte','identidade','nome','name','shortDescription','longDescription','namerelation'],
            'Datos Personales' => ['dataNascimento','genero','raca','escolaridade','renda','pessoaDeficiente','comunidades'],
            'Contacto'        => ['email','telefone','fax','site','facebook','twitter','instagram','youtube','linkedin','vimeo','spotify'],
            'Dirección'       => ['En_','address','endereco','CEP','cep','municipio','estado','pais','location'],
        ];

        // Propiedades nativas por entidad (las que no aparecen via getRegisteredMetadata)
        // Cada entidad tiene solo los campos que realmente le corresponden
        $nativePropertiesByEntity = [
            'MapasCulturais\\Entities\\Agent' => [
                'name'             => ['label' => 'Nombre Principal',              'type' => 'text', 'group' => 'Identificación'],
                'shortDescription' => ['label' => 'Descripción Corta',             'type' => 'text', 'group' => 'Identificación'],
                'longDescription'  => ['label' => 'Biografía / Descripción Larga', 'type' => 'text', 'group' => 'Identificación'],
                'namerelation'     => ['label' => 'Relación con el nombre',        'type' => 'text', 'group' => 'Identificación'],
                'site'             => ['label' => 'Sitio Web',                     'type' => 'text', 'group' => 'Contacto'],
                'emailPrivate'     => ['label' => 'Email Privado',                 'type' => 'text', 'group' => 'Contacto'],
                'emailPublic'      => ['label' => 'Email Público',                 'type' => 'text', 'group' => 'Contacto'],
                'telefone1'        => ['label' => 'Teléfono 1',                    'type' => 'text', 'group' => 'Contacto'],
                'telefone2'        => ['label' => 'Teléfono 2',                    'type' => 'text', 'group' => 'Contacto'],
                'En_Estado'        => ['label' => 'Departamento',                  'type' => 'text', 'group' => 'Dirección'],
                'location'         => ['label' => 'Ubicación en Mapa',             'type' => 'text', 'group' => 'Dirección'],
            ],
            'MapasCulturais\\Entities\\Space' => [
                'name'             => ['label' => 'Nombre del Espacio',            'type' => 'text', 'group' => 'Identificación'],
                'shortDescription' => ['label' => 'Descripción Corta',             'type' => 'text', 'group' => 'Identificación'],
                'longDescription'  => ['label' => 'Descripción Completa',          'type' => 'text', 'group' => 'Identificación'],
                'site'             => ['label' => 'Sitio Web',                     'type' => 'text', 'group' => 'Contacto'],
                'emailPrivate'     => ['label' => 'Email Privado',                 'type' => 'text', 'group' => 'Contacto'],
                'emailPublic'      => ['label' => 'Email Público',                 'type' => 'text', 'group' => 'Contacto'],
                'telefone1'        => ['label' => 'Teléfono 1',                    'type' => 'text', 'group' => 'Contacto'],
                'telefone2'        => ['label' => 'Teléfono 2',                    'type' => 'text', 'group' => 'Contacto'],
                'En_Estado'        => ['label' => 'Departamento',                  'type' => 'text', 'group' => 'Dirección'],
                'location'         => ['label' => 'Ubicación en Mapa',             'type' => 'text', 'group' => 'Dirección'],
            ],
            'MapasCulturais\\Entities\\Project' => [
                'name'             => ['label' => 'Nombre del Proyecto',           'type' => 'text', 'group' => 'Identificación'],
                'shortDescription' => ['label' => 'Descripción Corta',             'type' => 'text', 'group' => 'Identificación'],
                'longDescription'  => ['label' => 'Descripción Completa',          'type' => 'text', 'group' => 'Identificación'],
                'site'             => ['label' => 'Sitio Web',                     'type' => 'text', 'group' => 'Contacto'],
                'emailPrivate'     => ['label' => 'Email Privado',                 'type' => 'text', 'group' => 'Contacto'],
                'emailPublic'      => ['label' => 'Email Público',                 'type' => 'text', 'group' => 'Contacto'],
            ],
            'MapasCulturais\\Entities\\Event' => [
                'name'             => ['label' => 'Nombre del Evento',             'type' => 'text', 'group' => 'Identificación'],
                'shortDescription' => ['label' => 'Descripción Corta',             'type' => 'text', 'group' => 'Identificación'],
                'longDescription'  => ['label' => 'Descripción Completa',          'type' => 'text', 'group' => 'Identificación'],
                'site'             => ['label' => 'Sitio Web',                     'type' => 'text', 'group' => 'Contacto'],
                'emailPrivate'     => ['label' => 'Email Privado',                 'type' => 'text', 'group' => 'Contacto'],
                'emailPublic'      => ['label' => 'Email Público',                 'type' => 'text', 'group' => 'Contacto'],
            ],
            'MapasCulturais\\Entities\\Opportunity' => [
                'name'             => ['label' => 'Nombre de la Oportunidad',      'type' => 'text', 'group' => 'Identificación'],
                'shortDescription' => ['label' => 'Descripción Corta',             'type' => 'text', 'group' => 'Identificación'],
                'longDescription'  => ['label' => 'Descripción Completa',          'type' => 'text', 'group' => 'Identificación'],
                'site'             => ['label' => 'Sitio Web',                     'type' => 'text', 'group' => 'Contacto'],
                'emailPrivate'     => ['label' => 'Email Privado',                 'type' => 'text', 'group' => 'Contacto'],
                'emailPublic'      => ['label' => 'Email Público',                 'type' => 'text', 'group' => 'Contacto'],
            ],
        ];

        foreach ($entities as $class => $label) {
            $metadata = $app->getRegisteredMetadata($class);
            $fields = [];
            
            // 1. Cargar metadatos dinámicos registrados
            if (is_array($metadata)) {
                foreach ($metadata as $key => $def) {
                    $group = 'Otros';
                    foreach ($groupKeywords as $groupName => $keywords) {
                        foreach ($keywords as $kw) {
                            if (stripos($key, $kw) !== false) {
                                $group = $groupName;
                                break 2;
                            }
                        }
                    }
                    $fields[$key] = [
                        'label'          => $def->label,
                        'original_label' => $def->label,
                        'required'       => isset($def->validations['required']),
                        'type'           => $def->type,
                        'group'          => $group,
                    ];
                }
            }
            
            // 2. Inyectar solo las propiedades nativas de ESTA entidad
            $nativeFields = $nativePropertiesByEntity[$class] ?? [];
            foreach ($nativeFields as $key => $def) {
                if (!isset($fields[$key])) {
                    $fields[$key] = [
                        'label'          => $def['label'],
                        'original_label' => $def['label'] . ' (Nativo)',
                        'required'       => false,
                        'type'           => $def['type'],
                        'group'          => $def['group'],
                    ];
                }
            }

            // Sort fields by group for stable display
            uasort($fields, function($a, $b) {
                $order = ['Identificación'=>0,'Datos Personales'=>1,'Contacto'=>2,'Dirección'=>3,'Otros'=>4];
                return ($order[$a['group']] ?? 99) <=> ($order[$b['group']] ?? 99);
            });
            
            $fieldsConfig[$class] = [
                'label'     => $label,
                'fields'    => $fields,
                'overrides' => $currentConfig[$class] ?? []
            ];
        }
        
        $this->render('index', ['entities' => $fieldsConfig]);
    }
    
    public function POST_save() {
        $app = App::i();
        $config = $this->data['config'] ?? [];
        
        // Validation Layer
        $validEntities = [
            'MapasCulturais\Entities\Agent',
            'MapasCulturais\Entities\Space',
            'MapasCulturais\Entities\Project',
            'MapasCulturais\Entities\Event',
            'MapasCulturais\Entities\Opportunity'
        ];
        
        // Allowed native fields that bypass the registered metadata check
        $allowedNativeFields = [
            'name', 'shortDescription', 'longDescription', 'namerelation', 
            'site', 'emailPrivate', 'emailPublic', 'telefone1', 'telefone2', 'telefone3', 
            'En_Estado', 'location', 'terms'
        ];

        $sanitizedConfig = [];

        foreach ($config as $entityClass => $fields) {
            if (!in_array($entityClass, $validEntities)) {
                continue;
            }

            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $fieldKey => $fieldConfig) {
                // Ensure field exists in original metadata OR is a known native field
                $isNative = in_array($fieldKey, $allowedNativeFields);
                $isMeta = $app->getRegisteredMetadataByMetakey($fieldKey, $entityClass);
                
                if (!$isMeta && !$isNative) {
                    continue; // Skip arbitrary invalid keys
                }

                $isEnabled = !(isset($fieldConfig['enabled']) && $fieldConfig['enabled'] == '0');
                $isRequired = (isset($fieldConfig['required']) && $fieldConfig['required'] == '1');
                
                if (!$isEnabled) {
                    $isRequired = false;
                }

                $sanitizedConfig[$entityClass][$fieldKey] = [
                    'label' => htmlspecialchars($fieldConfig['label'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'required' => $isRequired,
                    'enabled' => $isEnabled
                ];
            }
        }
        
        $json = json_encode($sanitizedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $configFile = BASE_PATH . 'files/config/field-overrides.json';
        
        // Ensure directory exists
        if (!is_dir(dirname($configFile))) {
            mkdir(dirname($configFile), 0755, true);
        }

        file_put_contents($configFile, $json);
        
        $app->redirect($app->createUrl('dynamic-field-config', 'index'));
    }
}
