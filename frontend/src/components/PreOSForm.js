import React, { useState, useEffect } from 'react';
import { Box, TextField, Button, Typography, FormGroup, FormControlLabel, Checkbox, Alert, CircularProgress } from '@mui/material';
import { Send as SendIcon } from '@mui/icons-material';
import apiService from '../services/api';

function PreOSForm({ onSuccess }) {
  const [nome, setNome] = useState('');
  const [telefone, setTelefone] = useState('');
  const [email, setEmail] = useState('');
  const [observacoes, setObservacoes] = useState('');
  const [servicos, setServicos] = useState([]);
  const [servicosSelecionados, setServicosSelecionados] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [loadingServicos, setLoadingServicos] = useState(true);

  useEffect(() => {
    carregarServicos();
  }, []);

  const carregarServicos = async () => {
    try {
      const response = await apiService.listarServicos();
      if (response.success) {
        setServicos(response.data);
      }
    } catch (error) {
      setError('Erro ao carregar serviços');
    } finally {
      setLoadingServicos(false);
    }
  };

  const handleServicoChange = (servicoId) => {
    setServicosSelecionados(prev => 
      prev.includes(servicoId) 
        ? prev.filter(id => id !== servicoId)
        : [...prev, servicoId]
    );
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (!nome || !telefone || servicosSelecionados.length === 0) {
      setError('Preencha nome, telefone e selecione pelo menos um serviço');
      return;
    }

    setLoading(true);
    try {
      const response = await apiService.criarPreOS({
        nome,
        telefone,
        email,
        observacoes,
        servicos: servicosSelecionados
      });

      if (response.success) {
        onSuccess(response.data);
      }
    } catch (error) {
      setError(error.message || 'Erro ao enviar orçamento');
    } finally {
      setLoading(false);
    }
  };

  if (loadingServicos) {
    return <Box sx={{ display: 'flex', justifyContent: 'center', p: 4 }}><CircularProgress /></Box>;
  }

  return (
    <Box component="form" onSubmit={handleSubmit}>
      <Typography variant="h5" sx={{ mb: 3, fontWeight: 600 }}>
        Solicitar Orçamento
      </Typography>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <TextField fullWidth label="Nome completo *" value={nome} onChange={(e) => setNome(e.target.value)} margin="normal" disabled={loading} />
      <TextField fullWidth label="Telefone (WhatsApp) *" value={telefone} onChange={(e) => setTelefone(e.target.value)} placeholder="(27) 99999-9999" margin="normal" disabled={loading} />
      <TextField fullWidth label="E-mail (opcional)" type="email" value={email} onChange={(e) => setEmail(e.target.value)} margin="normal" disabled={loading} />

      <Typography variant="h6" sx={{ mt: 3, mb: 1 }}>Serviços desejados *</Typography>

      <FormGroup>
        {servicos.map((servico) => (
          <FormControlLabel
            key={servico.id}
            control={<Checkbox checked={servicosSelecionados.includes(servico.id)} onChange={() => handleServicoChange(servico.id)} disabled={loading} />}
            label={`${servico.nome} - ${servico.descricao || ''}`}
          />
        ))}
      </FormGroup>

      <TextField fullWidth label="Observações (opcional)" multiline rows={3} value={observacoes} onChange={(e) => setObservacoes(e.target.value)} margin="normal" disabled={loading} />

      <Button type="submit" fullWidth variant="contained" size="large" disabled={loading} startIcon={<SendIcon />} sx={{ mt: 3, py: 1.5 }}>
        {loading ? 'Enviando...' : 'Enviar Pedido'}
      </Button>
    </Box>
  );
}

export default PreOSForm;
