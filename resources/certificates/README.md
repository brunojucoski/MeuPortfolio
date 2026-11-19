# Certificado da conexao PostgreSQL

Depois de criar o projeto Supabase, baixe o certificado CA na configuracao do banco.
Salve somente o certificado publico como `supabase-ca.crt` nesta pasta e configure
`DB_SSLROOTCERT=/app/resources/certificates/supabase-ca.crt` no Vercel.

O certificado CA e publico e pode integrar a imagem. Nao inclua chaves privadas,
senhas ou arquivos de credenciais. Nao desative a validacao TLS para contornar erros.
