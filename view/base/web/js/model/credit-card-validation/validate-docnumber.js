/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* @api */
define([], function () {
    'use strict';

    function validateCPF(cpf)
    {
        var numeros, digitos, soma, i, resultado, digitos_iguais;
        digitos_iguais = 1;
        if (cpf.length < 11)
            return false;
        for (i = 0; i < cpf.length - 1; i++)
            if (cpf.charAt(i) != cpf.charAt(i + 1))
            {
                digitos_iguais = 0;
                break;
            }
        if (!digitos_iguais)
        {
            numeros = cpf.substring(0,9);
            digitos = cpf.substring(9);
            soma = 0;
            for (i = 10; i > 1; i--)
                soma += numeros.charAt(10 - i) * i;
            resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
            if (resultado != digitos.charAt(0))
                return false;
            numeros = cpf.substring(0,10);
            soma = 0;
            for (i = 11; i > 1; i--)
                soma += numeros.charAt(11 - i) * i;
            resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
            if (resultado != digitos.charAt(1))
                return false;
            return true;
        }
        else
            return false;
    }

    function validateCNPJ(cnpj)
    {
        var tamanho, numeros, digitos, pos, resultado, soma, i;

        if(cnpj == '') return false;

        if (cnpj.length != 14)
            return false;

        // Valida DVs
        tamanho = cnpj.length - 2
        numeros = cnpj.substring(0,tamanho);
        digitos = cnpj.substring(tamanho);
        soma = 0;
        pos = tamanho - 7;
        for (i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2)
                pos = 9;
        }
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(0))
            return false;

        tamanho = tamanho + 1;
        numeros = cnpj.substring(0,tamanho);
        soma = 0;
        pos = tamanho - 7;
        for (i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2)
                pos = 9;
        }
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(1))
            return false;

        return true;

    }
    
    return function (value) {
        value = value.replace(/[^\d]+/g,'');
        var response;

        if (value.length > 11) {

            response = {
                message: 'CNPJ Inválido',
                type: 'cnpj',
                isValid: false
            };

            if (validateCNPJ(value)) {
                response = {
                    message: '',
                    type: 'cnpj',
                    isValid: true
                };
            }

        } else {
            response = {
                message: 'CPF Inválido',
                type: 'cpf',
                isValid: false
            };

            if (validateCPF(value)) {
                response = {
                    message: '',
                    type: 'cpf',
                    isValid: true
                };
            }
        }

        return response;

    }

});