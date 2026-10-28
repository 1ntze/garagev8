-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 28, 2026 at 01:18 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `garage_v8`
--

-- --------------------------------------------------------

--
-- Table structure for table `atendimentos`
--

CREATE TABLE `atendimentos` (
  `id_atendimento` int NOT NULL,
  `id_cliente` int NOT NULL,
  `id_veiculo` int NOT NULL,
  `data_atendimento` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo_atendimento` enum('Orçamento','Agendamento','Realizado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Orçamento',
  `status` enum('Pendente','Em andamento','Concluído','Cancelado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendente',
  `observacoes` text COLLATE utf8mb4_general_ci,
  `valor_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_criacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cadastrado_por` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `atendimentos`
--

INSERT INTO `atendimentos` (`id_atendimento`, `id_cliente`, `id_veiculo`, `data_atendimento`, `tipo_atendimento`, `status`, `observacoes`, `valor_total`, `data_criacao`, `cadastrado_por`) VALUES
(1, 2, 1, '2026-10-19 10:41:00', 'Orçamento', 'Pendente', 'Orçamento valido por 7 dias', 300.00, '2026-10-18 21:42:05', 1);

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci,
  `cep` varchar(9) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logradouro` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `complemento` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bairro` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cidade` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` char(2) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_cadastro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cadastrado_por` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `nome`, `telefone`, `email`, `observacoes`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `data_cadastro`, `cadastrado_por`) VALUES
(1, 'RENAN LUIZ', '12991514184', NULL, 'CLIENTE DE CUNHA', '12530610', 'Rua Maria Francisca de Jesus', NULL, NULL, 'Areão', 'Cunha', 'SP', '2026-10-18 21:06:25', 1),
(2, 'NATHALIA ROSENDO', '12000000000', 'CLIENTE@CLIENTE.COM', 'SOBRINHA DO DONO, NÃO COBRAR PELO SERVIÇO KKKKK', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-18 21:09:17', 1),
(3, 'renan teste cep', '12000000000', 'CLIENTE@CLIENTE.COM', 'teste de atualizao de cadastro', '12524203', 'Travessa São Paulo', 's/n00', 'casa 1', 'Granja Patury', 'Guaratinguetá', 'SP', '2026-10-27 21:14:55', 1),
(4, 'casa que vamos roubar', NULL, NULL, 'descobrir o numero da casa', '12515135', 'Rua Caramurus', NULL, NULL, 'Pedregulho', 'Guaratinguetá', 'SP', '2026-10-27 21:21:36', 1);

-- --------------------------------------------------------

--
-- Table structure for table `controle_financeiro`
--

CREATE TABLE `controle_financeiro` (
  `id_financeiro` int NOT NULL,
  `tipo` enum('Receita','Despesa') COLLATE utf8mb4_general_ci NOT NULL,
  `descricao` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `referencia_id` int DEFAULT NULL,
  `referencia_tabela` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('Realizado','Pendente','Cancelado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendente',
  `observacoes` text COLLATE utf8mb4_general_ci,
  `cadastrado_por` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `despesas`
--

CREATE TABLE `despesas` (
  `id_despesa` int NOT NULL,
  `descricao` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_despesa` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `forma_pagamento` enum('Dinheiro','Pix','Cartão','Transferência','Outro') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('Paga','Pendente','Cancelada') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendente',
  `fornecedor` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci,
  `cadastrado_por` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `despesas`
--

INSERT INTO `despesas` (`id_despesa`, `descricao`, `categoria`, `valor`, `data_despesa`, `data_pagamento`, `forma_pagamento`, `status`, `fornecedor`, `observacoes`, `cadastrado_por`) VALUES
(1, 'Compra de Produto', 'Produtos e materiais', 150.00, '2026-10-19', '2026-10-19', 'Cartão', 'Paga', 'EMBALAGENS TAUBATE', NULL, 1),
(2, 'Compra de Produto', 'Manutenção', 100.00, '2026-10-20', '2026-10-21', 'Dinheiro', 'Paga', 'EMBALAGENS TAUBATE', NULL, 1),
(3, 'internet', 'Internet e telefone', 50.00, '2026-10-10', '2026-10-27', 'Pix', 'Paga', 'dbnet', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `itens_atendimento`
--

CREATE TABLE `itens_atendimento` (
  `id_item` int NOT NULL,
  `id_atendimento` int NOT NULL,
  `id_servico` int NOT NULL,
  `quantidade` int NOT NULL DEFAULT '1',
  `valor_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `custo_produto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `custo_material` decimal(10,2) NOT NULL DEFAULT '0.00',
  `observacoes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `itens_atendimento`
--

INSERT INTO `itens_atendimento` (`id_item`, `id_atendimento`, `id_servico`, `quantidade`, `valor_unitario`, `valor_total`, `custo_produto`, `custo_material`, `observacoes`) VALUES
(1, 1, 2, 1, 50.00, 50.00, 0.00, 0.00, NULL),
(2, 1, 1, 1, 250.00, 250.00, 0.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pagamentos`
--

CREATE TABLE `pagamentos` (
  `id_pagamento` int NOT NULL,
  `id_atendimento` int NOT NULL,
  `valor_pago` decimal(10,2) NOT NULL,
  `forma_pagamento` enum('Dinheiro','Pix','Cartão','Transferência','Outro') COLLATE utf8mb4_general_ci NOT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `status` enum('Pago','Pendente','Cancelado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendente',
  `observacoes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pagamentos`
--

INSERT INTO `pagamentos` (`id_pagamento`, `id_atendimento`, `valor_pago`, `forma_pagamento`, `data_pagamento`, `status`, `observacoes`) VALUES
(1, 1, 100.00, 'Pix', '2026-10-19 10:47:00', 'Pago', 'primeira parcela'),
(2, 1, 200.00, 'Dinheiro', '2026-10-20 12:00:00', 'Pago', 'segunda parcela');

-- --------------------------------------------------------

--
-- Table structure for table `relatorios`
--

CREATE TABLE `relatorios` (
  `id_relatorio` int NOT NULL,
  `tipo` enum('Faturamento','Despesas','Lucro','Clientes','Serviços') COLLATE utf8mb4_general_ci NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fim` date NOT NULL,
  `gerado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gerado_por` int NOT NULL,
  `arquivo_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `servicos`
--

CREATE TABLE `servicos` (
  `id_servico` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_general_ci,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `valor_referencia` decimal(10,2) NOT NULL DEFAULT '0.00',
  `duracao_estimada` int DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `servicos`
--

INSERT INTO `servicos` (`id_servico`, `nome`, `descricao`, `categoria`, `valor_referencia`, `duracao_estimada`, `ativo`) VALUES
(1, 'Polimento Tecnico', 'polimento automotivo ..../..../....', 'Extetica automotiva', 250.00, 120, 1),
(2, 'Lavagem Simples', 'lavagem basica sem detalhamento', 'Estetica automotiva', 50.00, 30, 1),
(3, 'teste', 'teste', 'teste', 100.00, 20, 1),
(4, 'teste 2', 'teste 2', 'Estetica automotiva', 100.00, 30, 1);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `usuario` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nivel_acesso` enum('admin','proprietario') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'proprietario',
  `ultimo_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nome`, `usuario`, `senha`, `nivel_acesso`, `ultimo_login`) VALUES
(1, 'Administrador', 'admin', '$2y$10$3SUwMJqNDLfNKNs0Po1PHOisMAj7wJJOn8fc7v22eodNw2qCKz/RG', 'admin', '2026-10-27 21:56:06'),
(2, 'USUARIO TESTE', 'teste.oficina', '$2y$10$nCjEEaPkgtuuto1HVvN5s.nDpqHMfVvOQY9nwSMl8wZkpcuckm8ZG', 'proprietario', '2026-10-21 22:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `veiculos`
--

CREATE TABLE `veiculos` (
  `id_veiculo` int NOT NULL,
  `id_cliente` int NOT NULL,
  `marca` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `modelo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `ano` int DEFAULT NULL,
  `placa` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cor` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `veiculos`
--

INSERT INTO `veiculos` (`id_veiculo`, `id_cliente`, `marca`, `modelo`, `ano`, `placa`, `cor`, `observacoes`) VALUES
(1, 2, 'Volkswagen', 'Fuscão', 1982, 'TES1T23', 'PRETO', 'QUANDO LIGADO DESCONSIDERAR A FUMAÇA E O BARRULHO DO MOTOR.\r\nOBS:USAR MASCARA KKKK');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `atendimentos`
--
ALTER TABLE `atendimentos`
  ADD PRIMARY KEY (`id_atendimento`),
  ADD KEY `fk_atendimentos_cliente` (`id_cliente`),
  ADD KEY `fk_atendimentos_veiculo` (`id_veiculo`),
  ADD KEY `fk_atendimentos_usuario` (`cadastrado_por`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD KEY `fk_clientes_usuario` (`cadastrado_por`);

--
-- Indexes for table `controle_financeiro`
--
ALTER TABLE `controle_financeiro`
  ADD PRIMARY KEY (`id_financeiro`),
  ADD KEY `fk_financeiro_usuario` (`cadastrado_por`);

--
-- Indexes for table `despesas`
--
ALTER TABLE `despesas`
  ADD PRIMARY KEY (`id_despesa`),
  ADD KEY `fk_despesas_usuario` (`cadastrado_por`);

--
-- Indexes for table `itens_atendimento`
--
ALTER TABLE `itens_atendimento`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `fk_itens_atendimento` (`id_atendimento`),
  ADD KEY `fk_itens_servico` (`id_servico`);

--
-- Indexes for table `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id_pagamento`),
  ADD KEY `fk_pagamentos_atendimento` (`id_atendimento`);

--
-- Indexes for table `relatorios`
--
ALTER TABLE `relatorios`
  ADD PRIMARY KEY (`id_relatorio`),
  ADD KEY `fk_relatorios_usuario` (`gerado_por`);

--
-- Indexes for table `servicos`
--
ALTER TABLE `servicos`
  ADD PRIMARY KEY (`id_servico`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indexes for table `veiculos`
--
ALTER TABLE `veiculos`
  ADD PRIMARY KEY (`id_veiculo`),
  ADD KEY `fk_veiculos_cliente` (`id_cliente`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `atendimentos`
--
ALTER TABLE `atendimentos`
  MODIFY `id_atendimento` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `controle_financeiro`
--
ALTER TABLE `controle_financeiro`
  MODIFY `id_financeiro` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `despesas`
--
ALTER TABLE `despesas`
  MODIFY `id_despesa` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `itens_atendimento`
--
ALTER TABLE `itens_atendimento`
  MODIFY `id_item` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id_pagamento` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `relatorios`
--
ALTER TABLE `relatorios`
  MODIFY `id_relatorio` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `servicos`
--
ALTER TABLE `servicos`
  MODIFY `id_servico` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `veiculos`
--
ALTER TABLE `veiculos`
  MODIFY `id_veiculo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `atendimentos`
--
ALTER TABLE `atendimentos`
  ADD CONSTRAINT `fk_atendimentos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `fk_atendimentos_usuario` FOREIGN KEY (`cadastrado_por`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_atendimentos_veiculo` FOREIGN KEY (`id_veiculo`) REFERENCES `veiculos` (`id_veiculo`);

--
-- Constraints for table `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_clientes_usuario` FOREIGN KEY (`cadastrado_por`) REFERENCES `usuarios` (`id_usuario`);

--
-- Constraints for table `controle_financeiro`
--
ALTER TABLE `controle_financeiro`
  ADD CONSTRAINT `fk_financeiro_usuario` FOREIGN KEY (`cadastrado_por`) REFERENCES `usuarios` (`id_usuario`);

--
-- Constraints for table `despesas`
--
ALTER TABLE `despesas`
  ADD CONSTRAINT `fk_despesas_usuario` FOREIGN KEY (`cadastrado_por`) REFERENCES `usuarios` (`id_usuario`);

--
-- Constraints for table `itens_atendimento`
--
ALTER TABLE `itens_atendimento`
  ADD CONSTRAINT `fk_itens_atendimento` FOREIGN KEY (`id_atendimento`) REFERENCES `atendimentos` (`id_atendimento`),
  ADD CONSTRAINT `fk_itens_servico` FOREIGN KEY (`id_servico`) REFERENCES `servicos` (`id_servico`);

--
-- Constraints for table `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD CONSTRAINT `fk_pagamentos_atendimento` FOREIGN KEY (`id_atendimento`) REFERENCES `atendimentos` (`id_atendimento`);

--
-- Constraints for table `relatorios`
--
ALTER TABLE `relatorios`
  ADD CONSTRAINT `fk_relatorios_usuario` FOREIGN KEY (`gerado_por`) REFERENCES `usuarios` (`id_usuario`);

--
-- Constraints for table `veiculos`
--
ALTER TABLE `veiculos`
  ADD CONSTRAINT `fk_veiculos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
