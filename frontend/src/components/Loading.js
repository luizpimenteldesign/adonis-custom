import React from 'react';
import { Box, CircularProgress, Typography } from '@mui/material';

function Loading({ message = 'Carregando...' }) {
  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', minHeight: '100vh', gap: 2 }}>
      <CircularProgress size={60} />
      <Typography variant="body1" color="text.secondary">{message}</Typography>
    </Box>
  );
}

export default Loading;
