import React, { useContext, useEffect, useState } from 'react';
import { Container, Box, Typography, Grid } from '@mui/material';
import { useNavigate } from 'react-router-dom';
import NavBar from '../components/NavBar';
import OSCard from '../components/OSCard';
import Loading from '../components/Loading';
import { AuthContext } from '../contexts/AuthContext';
import apiService from '../services/api';

function OSListPage() {
  const { user, logout } = useContext(AuthContext);
  const navigate = useNavigate();
  const [preOSList, setPreOSList] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!user) {
      navigate('/login');
      return;
    }
    carregarPreOS();
  }, [user, navigate]);

  const carregarPreOS = async () => {
    try {
      const response = await apiService.listarPreOS();
      if (response.success) setPreOSList(response.data);
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

  if (loading) return <Loading message="Carregando..." />;

  return (
    <>
      <NavBar user={user} onLogout={handleLogout} />
      <Container maxWidth="lg">
        <Box sx={{ py: 4 }}>
          <Typography variant="h4" sx={{ mb: 4, fontWeight: 600 }}>Pré-OS Recebidas</Typography>
          {preOSList.length === 0 ? (
            <Typography variant="body1" color="text.secondary" sx={{ textAlign: 'center', mt: 4 }}>Nenhuma Pré-OS encontrada</Typography>
          ) : (
            <Grid container spacing={3}>
              {preOSList.map((os) => (
                <Grid item xs={12} sm={6} md={4} key={os.id}>
                  <OSCard os={os} onClick={(os) => navigate(`/os/${os.id}`)} />
                </Grid>
              ))}
            </Grid>
          )}
        </Box>
      </Container>
    </>
  );
}

export default OSListPage;
