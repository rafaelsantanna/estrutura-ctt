# CTT Address Data Import

Sistema de importação de dados dos códios postais CTT (Portugal) para Laravel.

## 📁 Estrutura

```
address-ctt/
├── database/migrations/
│   └── 2025_01_19_200000_create_final_address_structure.php
├── app/Console/Commands/
│   └── ImportCttData.php
├── todos_cp/
│   ├── distritos.txt
│   ├── concelhos.txt
│   ├── todos_cp.txt
│   └── leiame.txt
└── README.md
```

## 🚀 Instalação

1. **Copie os arquivos para seu projeto Laravel:**
   ```bash
   cp database/migrations/* your-project/database/migrations/
   cp app/Console/Commands/* your-project/app/Console/Commands/
   cp -r todos_cp your-project/
   ```

2. **Execute a migration:**
   ```bash
   php artisan migrate
   ```

3. **Importe os dados CTT:**
   ```bash
   php artisan import:ctt-data --force
   ```

## 📊 Estrutura de Dados

O sistema cria 5 tabelas:

- **distritos** (20 registros)
- **concelhos** (300+ registros)  
- **freguesias** (4k+ registros)
- **localidades** (50k+ registros)
- **codigos_postais** (300k+ registros)

## ⚡ Comando de Importação

```bash
# Importação básica
php artisan import:ctt-data

# Opções avançadas
php artisan import:ctt-data --force --batch-size=5000 --memory-limit=512
```

### Parâmetros:
- `--force`: Limpa dados existentes
- `--batch-size`: Tamanho do lote (default: 5000)
- `--memory-limit`: Limite de memória em MB (default: 256)

## 🔍 Queries Exemplo

```php
// Buscar por código postal
DB::table('codigos_postais')
    ->where('cp4', '3750')
    ->where('cp3', '011')
    ->first();

// Listar distritos
DB::table('distritos')->orderBy('nome')->get();

// Concelhos de um distrito
DB::table('concelhos')
    ->where('codigo_distrito', '01')
    ->orderBy('nome')
    ->get();
```

## 📝 Requisitos

- PHP 8.0+
- Laravel 9.0+
- MySQL/MariaDB
- 512MB+ RAM disponível
- ~500MB espaço em disco

## ⏱️ Performance

- Importação: ~2-5 minutos
- Memória: ~256MB
- Espaço BD: ~150MB
- Índices otimizados para busca rápida

---

**Dados fonte:** CTT Correios de Portugal