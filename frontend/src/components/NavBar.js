import React from 'react';
import { AppBar, Toolbar, Typography, Button, Box } from '@mui/material';
import { Dashboard, ExitToApp } from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';

function NavBar({ user, onLogout }) {
  const navigate = useNavigate();

  return (
    <AppBar position="static">
      <Toolbar>
        <Typography variant="h6" sx={{ flexGrow: 1, fontWeight: 600 }}>Adonis Custom - OS</Typography>
        {user && (
          <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
            <Typography variant="body2">Olá, {user.nome}</Typography>
            <Button color="inherit" startIcon={<Dashboard />} onClick={() => navigate('/dashboard')}>Dashboard</Button>
            <Button color="inherit" startIcon={<ExitToApp />} onClick={onLogout}>Sair</Button>
          </Box>
        )}
      </Toolbar>
    </AppBar>
  );
}

export default NavBar;
