/**
 * EXEMPLO DE FORMULÁRIO DE ENDEREÇO OTIMIZADO
 * Usa a API simplificada sem JOINs complexos
 * Performance máxima com cache inteligente
 */

class OptimizedAddressForm {
    constructor(formElement, options = {}) {
        this.form = formElement;
        this.apiBase = options.apiBase || '/api';
        this.cache = new Map();
        this.debounceTimeout = null;
        
        this.initializeElements();
        this.attachEventListeners();
        this.loadDistritos();
    }
    
    initializeElements() {
        // Elementos do formulário
        this.elements = {
            distrito: this.form.querySelector('[name="distrito"]'),
            concelho: this.form.querySelector('[name="concelho"]'),
            localidade: this.form.querySelector('[name="localidade"]'),
            codigoPostal: this.form.querySelector('[name="codigo_postal"]'),
            morada: this.form.querySelector('[name="morada"]'),
            
            // Campos auto-preenchidos
            distritoNome: this.form.querySelector('[name="distrito_nome"]'),
            concelhoNome: this.form.querySelector('[name="concelho_nome"]'),
            localidadeNome: this.form.querySelector('[name="localidade_nome"]')
        };
        
        // Elementos de autocomplete
        this.autocompleteContainers = {
            cp: this.form.querySelector('.cp-autocomplete'),
            localidade: this.form.querySelector('.localidade-autocomplete')
        };
    }
    
    attachEventListeners() {
        // Seleção hierárquica
        this.elements.distrito?.addEventListener('change', () => this.onDistritoChange());
        this.elements.concelho?.addEventListener('change', () => this.onConcelhoChange());
        this.elements.localidade?.addEventListener('change', () => this.onLocalidadeChange());
        
        // Preenchimento automático por CP
        this.elements.codigoPostal?.addEventListener('input', (e) => this.onCodigoPostalInput(e));
        this.elements.codigoPostal?.addEventListener('blur', (e) => this.onCodigoPostalBlur(e));
        
        // Autocomplete de localidades
        this.elements.localidadeNome?.addEventListener('input', (e) => this.onLocalidadeInput(e));
    }
    
    // ===== CARREGAMENTO HIERÁRQUICO =====
    
    async loadDistritos() {
        try {
            const distritos = await this.apiCall('/distritos');
            this.populateSelect(this.elements.distrito, distritos, 'codigo', 'nome', 'Selecione o distrito');
        } catch (error) {
            console.error('Erro ao carregar distritos:', error);
        }
    }
    
    async onDistritoChange() {
        const codigoDistrito = this.elements.distrito.value;
        
        // Limpar seleções dependentes
        this.clearSelect(this.elements.concelho, 'Selecione o concelho');
        this.clearSelect(this.elements.localidade, 'Selecione a localidade');
        
        if (!codigoDistrito) return;
        
        try {
            const concelhos = await this.apiCall(`/concelhos/${codigoDistrito}`);
            this.populateSelect(this.elements.concelho, concelhos, 'codigo_concelho', 'nome', 'Selecione o concelho');
            
            // Auto-preencher nome do distrito
            const distritoNome = this.elements.distrito.options[this.elements.distrito.selectedIndex].text;
            if (this.elements.distritoNome) {
                this.elements.distritoNome.value = distritoNome;
            }
        } catch (error) {
            console.error('Erro ao carregar concelhos:', error);
        }
    }
    
    async onConcelhoChange() {
        const codigoDistrito = this.elements.distrito.value;
        const codigoConcelho = this.elements.concelho.value;
        
        this.clearSelect(this.elements.localidade, 'Selecione a localidade');
        
        if (!codigoDistrito || !codigoConcelho) return;
        
        try {
            const localidades = await this.apiCall(`/localidades/${codigoDistrito}/${codigoConcelho}`);
            this.populateSelect(this.elements.localidade, localidades, 'codigo_localidade', 'nome', 'Selecione a localidade');
            
            // Auto-preencher nome do concelho
            const concelhoNome = this.elements.concelho.options[this.elements.concelho.selectedIndex].text;
            if (this.elements.concelhoNome) {
                this.elements.concelhoNome.value = concelhoNome;
            }
        } catch (error) {
            console.error('Erro ao carregar localidades:', error);
        }
    }
    
    onLocalidadeChange() {
        // Auto-preencher nome da localidade
        if (this.elements.localidade.value && this.elements.localidadeNome) {
            const localidadeNome = this.elements.localidade.options[this.elements.localidade.selectedIndex].text;
            this.elements.localidadeNome.value = localidadeNome;
        }
    }
    
    // ===== PREENCHIMENTO AUTOMÁTICO POR CP =====
    
    onCodigoPostalInput(event) {
        const value = event.target.value.replace(/\D/g, ''); // Apenas números
        
        // Formatar automaticamente: 0000-000
        if (value.length > 4) {
            event.target.value = value.slice(0, 4) + '-' + value.slice(4, 7);
        }
        
        // Autocomplete de CP
        if (value.length >= 3) {
            this.debounceAutocompleteCP(value.slice(0, 4));
        }
    }
    
    debounceAutocompleteCP(query) {
        clearTimeout(this.debounceTimeout);
        this.debounceTimeout = setTimeout(() => {
            this.loadAutocompleteCP(query);
        }, 300);
    }
    
    async loadAutocompleteCP(query) {
        if (!this.autocompleteContainers.cp) return;
        
        try {
            const results = await this.apiCall(`/autocomplete/cp?q=${query}`);
            this.showAutocompleteCP(results);
        } catch (error) {
            console.error('Erro no autocomplete de CP:', error);
        }
    }
    
    showAutocompleteCP(results) {
        const container = this.autocompleteContainers.cp;
        container.innerHTML = '';
        
        if (results.length === 0) {
            container.style.display = 'none';
            return;
        }
        
        const ul = document.createElement('ul');
        ul.className = 'autocomplete-list';
        
        results.forEach(result => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            li.textContent = result.label;
            li.addEventListener('click', () => {
                this.selectCodigoPostal(result.codigo_postal);
                container.style.display = 'none';
            });
            ul.appendChild(li);
        });
        
        container.appendChild(ul);
        container.style.display = 'block';
    }
    
    async onCodigoPostalBlur(event) {
        const codigoPostal = event.target.value;
        
        if (codigoPostal.length === 8) { // 0000-000
            await this.autoFillFromCP(codigoPostal);
        }
    }
    
    async selectCodigoPostal(codigoPostal) {
        this.elements.codigoPostal.value = codigoPostal;
        await this.autoFillFromCP(codigoPostal);
    }
    
    async autoFillFromCP(codigoPostal) {
        const parts = codigoPostal.replace('-', '').match(/(\d{4})(\d{3})/);
        if (!parts) return;
        
        const [, cp4, cp3] = parts;
        
        try {
            const endereco = await this.apiCall(`/codigo-postal/${cp4}/${cp3}`);
            
            if (endereco) {
                // Preencher automaticamente todos os campos
                this.autoFillFromEnderecoData(endereco);
                this.showSuccessMessage('Endereço preenchido automaticamente!');
            } else {
                this.showErrorMessage('Código postal não encontrado.');
            }
        } catch (error) {
            console.error('Erro ao buscar CP:', error);
            this.showErrorMessage('Erro ao validar código postal.');
        }
    }
    
    autoFillFromEnderecoData(endereco) {
        // Preencher campos de texto
        if (this.elements.distritoNome) this.elements.distritoNome.value = endereco.nome_distrito;
        if (this.elements.concelhoNome) this.elements.concelhoNome.value = endereco.nome_concelho;
        if (this.elements.localidadeNome) this.elements.localidadeNome.value = endereco.nome_localidade;
        
        // Sugerir morada se houver informação de via
        if (endereco.tipo_via && endereco.nome_via && this.elements.morada) {
            const moradaSugerida = `${endereco.tipo_via} ${endereco.nome_via}`;
            if (!this.elements.morada.value) {
                this.elements.morada.value = moradaSugerida;
            }
        }
    }
    
    // ===== AUTOCOMPLETE DE LOCALIDADES =====
    
    onLocalidadeInput(event) {
        const query = event.target.value;
        
        if (query.length >= 3) {
            clearTimeout(this.debounceTimeout);
            this.debounceTimeout = setTimeout(() => {
                this.loadAutocompleteLocalidades(query);
            }, 300);
        }
    }
    
    async loadAutocompleteLocalidades(query) {
        if (!this.autocompleteContainers.localidade) return;
        
        try {
            const results = await this.apiCall(`/autocomplete/localidades?q=${encodeURIComponent(query)}`);
            this.showAutocompleteLocalidades(results);
        } catch (error) {
            console.error('Erro no autocomplete de localidades:', error);
        }
    }
    
    showAutocompleteLocalidades(results) {
        const container = this.autocompleteContainers.localidade;
        container.innerHTML = '';
        
        if (results.length === 0) {
            container.style.display = 'none';
            return;
        }
        
        const ul = document.createElement('ul');
        ul.className = 'autocomplete-list';
        
        results.forEach(result => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            li.textContent = `${result.nome} (${result.codigo_completo})`;
            li.addEventListener('click', () => {
                this.selectLocalidade(result);
                container.style.display = 'none';
            });
            ul.appendChild(li);
        });
        
        container.appendChild(ul);
        container.style.display = 'block';
    }
    
    selectLocalidade(localidade) {
        if (this.elements.localidadeNome) {
            this.elements.localidadeNome.value = localidade.nome;
        }
    }
    
    // ===== UTILITÁRIOS =====
    
    async apiCall(endpoint) {
        // Cache simples
        if (this.cache.has(endpoint)) {
            return this.cache.get(endpoint);
        }
        
        const response = await fetch(`${this.apiBase}${endpoint}`, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Cache por 5 minutos
        this.cache.set(endpoint, data);
        setTimeout(() => this.cache.delete(endpoint), 5 * 60 * 1000);
        
        return data;
    }
    
    populateSelect(selectElement, options, valueField, textField, defaultText) {
        if (!selectElement) return;
        
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;
        
        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option[valueField];
            optionElement.textContent = option[textField];
            selectElement.appendChild(optionElement);
        });
    }
    
    clearSelect(selectElement, defaultText) {
        if (!selectElement) return;
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;
    }
    
    showSuccessMessage(message) {
        // Implementar notificação de sucesso
        console.log('✓', message);
    }
    
    showErrorMessage(message) {
        // Implementar notificação de erro  
        console.error('❌', message);
    }
}

// ===== EXEMPLO DE USO =====

// Inicializar formulário quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('address-form');
    if (form) {
        new OptimizedAddressForm(form, {
            apiBase: '/api' // Ajustar conforme necessário
        });
    }
});

/*
=== HTML ESPERADO ===

<form id="address-form">
    <!-- Seleção hierárquica -->
    <select name="distrito">
        <option value="">Carregando...</option>
    </select>
    
    <select name="concelho">
        <option value="">Selecione o distrito primeiro</option>
    </select>
    
    <select name="localidade">
        <option value="">Selecione o concelho primeiro</option>
    </select>
    
    <!-- Código postal com autocomplete -->
    <div class="cp-input-container">
        <input type="text" name="codigo_postal" placeholder="0000-000" maxlength="8">
        <div class="cp-autocomplete" style="display: none;"></div>
    </div>
    
    <!-- Localidade com autocomplete -->
    <div class="localidade-input-container">
        <input type="text" name="localidade_nome" placeholder="Digite o nome da localidade">
        <div class="localidade-autocomplete" style="display: none;"></div>
    </div>
    
    <!-- Campos auto-preenchidos (hidden ou readonly) -->
    <input type="hidden" name="distrito_nome">
    <input type="hidden" name="concelho_nome">
    
    <!-- Morada -->
    <input type="text" name="morada" placeholder="Rua, número, andar">
</form>

=== CSS SUGERIDO ===

.autocomplete-list {
    list-style: none;
    padding: 0;
    margin: 0;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    max-height: 200px;
    overflow-y: auto;
}

.autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

.autocomplete-item:hover {
    background-color: #f5f5f5;
}

.autocomplete-item:last-child {
    border-bottom: none;
}
*/