import React, { useState } from 'react';
import { Container, Box, Paper, Typography, Alert, Button } from '@mui/material';
import { CheckCircle, Home } from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import PreOSForm from '../components/PreOSForm';

function PreOSPage() {
  const [sucesso, setSucesso] = useState(false);
  const [dadosPreOS, setDadosPreOS] = useState(null);
  const navigate = useNavigate();

  const handleSuccess = (dados) => {
    setDadosPreOS(dados);
    setSucesso(true);
  };

  if (sucesso) {
    return (
      <Container maxWidth="sm">
        <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <Paper elevation={3} sx={{ p: 4, textAlign: 'center' }}>
            <CheckCircle sx={{ fontSize: 80, color: 'success.main', mb: 2 }} />
            <Typography variant="h5" sx={{ mb: 2, fontWeight: 600 }}>Pedido Enviado com Sucesso!</Typography>
            <Alert severity="success" sx={{ mb: 3, textAlign: 'left' }}>
              <Typography variant="body2"><strong>Número:</strong> {dadosPreOS?.numero_pre_os}</Typography>
              <Typography variant="body2"><strong>Valor estimado:</strong> R$ {dadosPreOS?.valor_estimado?.toFixed(2)}</Typography>
            </Alert>
            <Button variant="contained" startIcon={<Home />} onClick={() => window.location.reload()}>Novo Orçamento</Button>
            <Button variant="text" onClick={() => navigate('/login')} sx={{ ml: 2 }}>Área Admin</Button>
          </Paper>
        </Box>
      </Container>
    );
  }

  return (
    <Container maxWidth="md">
      <Box sx={{ py: 4 }}>
        <Paper elevation={3} sx={{ p: 4 }}>
          <PreOSForm onSuccess={handleSuccess} />
        </Paper>
        <Box sx={{ mt: 2, textAlign: 'center' }}>
          <Button variant="text" size="small" onClick={() => navigate('/login')}>Acesso Admin</Button>
        </Box>
      </Box>
    </Container>
  );
}

export default PreOSPage;
