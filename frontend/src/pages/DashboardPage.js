import React, { useContext, useEffect, useState } from 'react';
import { Container, Box, Typography, Button } from '@mui/material';
import { useNavigate } from 'react-router-dom';
import { Add } from '@mui/icons-material';
import NavBar from '../components/NavBar';
import Dashboard from '../components/Dashboard';
import Loading from '../components/Loading';
import { AuthContext } from '../contexts/AuthContext';
import apiService from '../services/api';

function DashboardPage() {
  const { user, logout } = useContext(AuthContext);
  const navigate = useNavigate();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!user) {
      navigate('/login');
      return;
    }
    carregarStats();
  }, [user, navigate]);

  const carregarStats = async () => {
    try {
      const response = await apiService.listarPreOS();
      if (response.success) {
        const data = response.data;
        const stats = {
          aguardando: data.filter(p => p.status === 'aguardando_analise').length,
          em_analise: data.filter(p => p.status === 'em_analise').length,
          orcadas: data.filter(p => p.status === 'orcada').length,
          aprovadas: data.filter(p => p.status === 'aprovada').length
        };
        setStats(stats);
      }
    } catch (error) {
      console.error('Erro:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  if (loading) return <Loading message="Carregando dashboard..." />;

  return (
    <>
      <NavBar user={user} onLogout={handleLogout} />
      <Container maxWidth="lg">
        <Box sx={{ py: 4 }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 4 }}>
            <Typography variant="h4" sx={{ fontWeight: 600 }}>Dashboard</Typography>
            <Button variant="contained" startIcon={<Add />} onClick={() => navigate('/os')}>Ver Pré-OS</Button>
          </Box>
          <Dashboard stats={stats} />
        </Box>
      </Container>
    </>
  );
}

export default DashboardPage;
