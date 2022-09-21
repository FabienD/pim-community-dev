import React, {FC, useState} from 'react';
import styled, {keyframes} from 'styled-components';
import {SectionTitle, SwitcherButton, Table} from 'akeneo-design-system';
import {useTranslate} from '@akeneo-pim-community/shared';

const slideIn = keyframes`
  from {
      flex-basis: 0;
      width: 0;
  }

  to {
      flex-basis: 50%;
  }
`;

const MappingContainer = styled.div`
    display: flex;
    gap: 40px;
`;
const TargetContainer = styled.div`
    flex-basis: 50%;
    flex-grow: 1;
`;
const SourceContainer = styled.div`
    flex-basis: 50%;
    animation: ${slideIn} 0.2s ease-in-out;
`;

const ColumnTitle = styled(SectionTitle)`
    margin: 10px 0;
`;

const TargetCell = styled(Table.Cell)`
    width: 215px;
`;

type Props = {};

export const ProductMapping: FC<Props> = () => {
    const translate = useTranslate();
    const targets = [
        {code: 'target1', name: 'Target One'},
        {code: 'target2', name: 'Target Two'},
    ];

    const [selectedTarget, setSelectedTarget] = useState<string>();

    return (
        <MappingContainer>
            <TargetContainer>
                <ColumnTitle>
                    <SectionTitle.Title>{translate('akeneo_catalogs.product_mapping.target.title')}</SectionTitle.Title>
                    <SectionTitle.Spacer />
                    <SwitcherButton label={translate('akeneo_catalogs.product_mapping.target.filter.label')}>
                        {translate('akeneo_catalogs.product_mapping.target.filter.option.all')}
                    </SwitcherButton>
                </ColumnTitle>
                <Table>
                    <Table.Header>
                        <Table.HeaderCell>
                            {translate('akeneo_catalogs.product_mapping.target.table.target')}
                        </Table.HeaderCell>
                        <Table.HeaderCell>
                            {translate('akeneo_catalogs.product_mapping.target.table.source')}
                        </Table.HeaderCell>
                    </Table.Header>
                    <Table.Body>
                        {targets.map(target => {
                            return (
                                <Table.Row
                                    key={target.code}
                                    onClick={() => {
                                        setSelectedTarget(target.code);
                                    }}
                                >
                                    <TargetCell>{target.name}</TargetCell>
                                    <Table.Cell></Table.Cell>
                                </Table.Row>
                            );
                        })}
                    </Table.Body>
                </Table>
            </TargetContainer>
            {selectedTarget && (
                <SourceContainer>
                    <ColumnTitle>
                        <SectionTitle.Title>Target Name</SectionTitle.Title>
                    </ColumnTitle>
                </SourceContainer>
            )}
        </MappingContainer>
    );
};
