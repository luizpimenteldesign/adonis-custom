import React from 'react';
import { Card, CardContent, Typography, Chip, Box, Button } from '@mui/material';
import { Visibility } from '@mui/icons-material';

function OSCard({ os, onClick }) {
  return (
    <Card>
      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
          <Box>
            <Typography variant="h6" sx={{ fontWeight: 600 }}>{os.numero_pre_os}</Typography>
            <Typography variant="body2" color="text.secondary">{os.cliente_nome}</Typography>
          </Box>
          <Chip label={os.status} color="primary" size="small" />
        </Box>
        <Typography variant="body2" sx={{ mb: 2 }}>Tel: {os.cliente_telefone}</Typography>
        <Button variant="outlined" size="small" startIcon={<Visibility />} onClick={() => onClick(os)} fullWidth>Ver Detalhes</Button>
      </CardContent>
    </Card>
  );
}

export default OSCard;
