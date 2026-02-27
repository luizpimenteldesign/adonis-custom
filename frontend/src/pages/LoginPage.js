import React, { useContext, useEffect } from 'react';
import { Container, Box, Paper } from '@mui/material';
import { useNavigate } from 'react-router-dom';
import LoginForm from '../components/LoginForm';
import { AuthContext } from '../contexts/AuthContext';

function LoginPage() {
  const { user, login, loading } = useContext(AuthContext);
  const navigate = useNavigate();

  useEffect(() => {
    if (user) navigate('/dashboard');
  }, [user, navigate]);

  const handleLogin = async (email, senha) => {
    const result = await login(email, senha);
    if (result.success) navigate('/dashboard');
    return result;
  };

  return (
    <Container maxWidth="sm">
      <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <Paper elevation={3} sx={{ p: 4, width: '100%', maxWidth: 400 }}>
          <LoginForm onLogin={handleLogin} loading={loading} />
        </Paper>
      </Box>
    </Container>
  );
}

export default LoginPage;
