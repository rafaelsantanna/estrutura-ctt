# Sistema de Endereços CTT para Laravel

Sistema de importação de dados de endereços dos CTT (Portugal) para Laravel.

## Instalação

1. Copie os arquivos para seu projeto Laravel:
```
- database/migrations/2025_01_19_200000_create_final_address_structure.php
- app/Console/Commands/ImportCttData.php
- todos_cp/ (diretório completo com os dados)
```

2. Execute a migration:
```bash
php artisan migrate
```

3. Importe os dados CTT:
```bash
php artisan import:ctt-data
```

## Estrutura de Tabelas

- **distritos** - 20 registros
- **concelhos** - 300+ registros  
- **freguesias** - 4k+ registros
- **localidades** - 50k+ registros
- **codigos_postais** - 300k+ registros

## Uso

### Buscar por Código Postal
```php
$endereco = DB::table('codigos_postais')
    ->where('cp4', '2710')
    ->where('cp3', '405')
    ->first();
```

### Listar Concelhos de um Distrito
```php
$concelhos = DB::table('concelhos')
    ->where('codigo_distrito', '13')
    ->get();
```

### Autocomplete de Localidades
```php
$localidades = DB::table('localidades')
    ->where('nome', 'like', 'Lisboa%')
    ->limit(10)
    ->get();
```

## Performance

- Importação: 5-15 minutos para 300k+ registros
- Memory: Máximo 256MB
- Sem memory leaks