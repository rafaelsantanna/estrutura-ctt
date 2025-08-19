# Sistema de Importação de Dados CTT - Laravel

## Estrutura das Tabelas

### 1. **distritos**
- `codigo` (PK) - Código do distrito (2 caracteres)
- `nome` - Nome do distrito

### 2. **concelhos**
- `id` (PK)
- `codigo_distrito` (FK) - Referência ao distrito
- `codigo_concelho` - Código do concelho (2 caracteres)
- `nome` - Nome do concelho

### 3. **localidades**
- `id` (PK)
- `codigo_distrito` (FK) - Referência ao distrito
- `codigo_concelho` - Código do concelho
- `codigo_localidade` - Código da localidade
- `nome` - Nome da localidade

### 4. **freguesias**
- `id` (PK)
- `codigo_distrito` (FK) - Referência ao distrito
- `codigo_concelho` - Código do concelho
- `nome` - Nome da freguesia

### 5. **arruamentos**
- `id` (PK)
- `localidade_id` (FK) - Referência à localidade
- `codigo_arteria` - Código da artéria
- `tipo` - Tipo (Rua, Praça, etc.)
- `primeira_preposicao` - Primeira preposição
- `titulo` - Título (Doutor, Eng.º, etc.)
- `segunda_preposicao` - Segunda preposição
- `designacao` - Designação da artéria
- `informacao_local` - Informação do local/zona
- `troco` - Descrição do troço
- `porta` - Número da porta
- `cliente` - Nome do cliente

### 6. **codigos_postais**
- `id` (PK)
- `codigo_distrito` (FK) - Referência ao distrito
- `codigo_concelho` - Código do concelho
- `codigo_localidade` - Código da localidade
- `localidade_id` (FK) - Referência à localidade
- `arruamento_id` (FK) - Referência ao arruamento (opcional)
- `cp4` - Primeiros 4 dígitos do código postal
- `cp3` - Últimos 3 dígitos do código postal
- `designacao_postal` - Designação postal

## Instalação

### 1. Executar as Migrations

```bash
php artisan migrate
```

### 2. Importar os Dados CTT

```bash
# Importação normal (preserva dados existentes)
php artisan import:ctt-data

# Forçar reimportação (limpa dados existentes)
php artisan import:ctt-data --force

# Usar caminho alternativo para os arquivos
php artisan import:ctt-data --path=/caminho/para/arquivos
```

## Uso no Frontend

### Exemplo de Fluxo de Seleção

1. **Listar Distritos:**
```sql
SELECT codigo, nome FROM distritos ORDER BY nome;
```

2. **Listar Concelhos de um Distrito:**
```sql
SELECT id, codigo_concelho, nome 
FROM concelhos 
WHERE codigo_distrito = '01' 
ORDER BY nome;
```

3. **Listar Freguesias de um Concelho:**
```sql
SELECT id, nome 
FROM freguesias 
WHERE codigo_distrito = '01' AND codigo_concelho = '01' 
ORDER BY nome;
```

4. **Listar Localidades de um Concelho:**
```sql
SELECT id, codigo_localidade, nome 
FROM localidades 
WHERE codigo_distrito = '01' AND codigo_concelho = '01' 
ORDER BY nome;
```

5. **Buscar por Código Postal:**
```sql
SELECT cp.*, l.nome as localidade, a.* 
FROM codigos_postais cp
LEFT JOIN localidades l ON cp.localidade_id = l.id
LEFT JOIN arruamentos a ON cp.arruamento_id = a.id
WHERE cp.cp4 = '3750' AND cp.cp3 = '011';
```

## Características do Sistema

- **Importação Incremental:** Por padrão, preserva dados existentes
- **Modo Force:** Opção para limpar e reimportar todos os dados
- **Transações:** Usa transações para garantir integridade
- **Progress Bar:** Mostra progresso durante importação
- **Encoding Automático:** Converte automaticamente para UTF-8
- **Performance:** Processa em lotes para melhor performance
- **Relacionamentos:** Mantém integridade referencial entre tabelas

## Estatísticas Esperadas

Após importação completa dos dados CTT, você terá aproximadamente:
- 20 Distritos
- 300+ Concelhos
- 4000+ Freguesias
- 50000+ Localidades
- 300000+ Códigos Postais

## Notas Importantes

1. Os arquivos de dados devem estar na pasta `todos_cp/` na raiz do projeto
2. Os arquivos necessários são:
   - `distritos.txt`
   - `concelhos.txt`
   - `todos_cp.txt`
3. O comando detecta automaticamente o encoding dos arquivos (ISO-8859-1 ou UTF-8)
4. Índices são criados para otimizar consultas por nome e códigos