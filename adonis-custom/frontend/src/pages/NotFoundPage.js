import React from 'react';
import { Container, Box, Typography, Button } from '@mui/material';
import { Home } from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';

function NotFoundPage() {
  const navigate = useNavigate();
  return (
    <Container maxWidth="sm">
      <Box sx={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
        <Typography variant="h1" sx={{ fontSize: 120, fontWeight: 700, color: 'primary.main' }}>404</Typography>
        <Typography variant="h5" sx={{ mb: 3 }}>Página não encontrada</Typography>
        <Button variant="contained" startIcon={<Home />} onClick={() => navigate('/')}>Voltar ao Início</Button>
      </Box>
    </Container>
  );
}

export default NotFoundPage;
